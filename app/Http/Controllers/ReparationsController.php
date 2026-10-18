<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reparation\AddStageRequest;
use App\Http\Requests\Reparation\ChangeReparationStatusRequest;
use App\Http\Requests\Reparation\ConfirmReparationRequest;
use App\Http\Requests\Reparation\StoreReparationRequest;
use App\Http\Requests\Reparation\UpdateReparationRequest;
use App\Http\Resources\ReparationsResource;
use App\Models\Customer;
use App\Models\Reparation;
use App\Models\ReparationLog;
use App\Models\ReparationStage;
use App\Support\ReparationPayment;
use App\Support\ReparationTracker;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Throwable;

class ReparationsController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->filled('from') ? $request->string('from')->toString() : null;
        $to = $request->filled('to') ? $request->string('to')->toString() : null;

        $reparations = Reparation::query()
            ->with(['customer:id,name,phone', 'stages.items'])
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('item_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('flag'), fn ($query) => $query->whereJsonContains('flag', $request->string('flag')->toString()))
            ->when($request->filled('status') === false, fn ($query) => $query->whereNotIn('status', ['DONE', 'CANCEL']))
            ->when($from, fn ($query) => $query->where('created_at', '>=', "{$from} 00:00:00"))
            ->when($to, fn ($query) => $query->where('created_at', '<=', "{$to} 23:59:59"))
            ->orderByRaw($this->activeDeadlineColumn().' IS NULL')
            ->orderByRaw($this->activeDeadlineColumn().' ASC')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Servis/Index', [
            'reparations' => ReparationsResource::collection($reparations),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'statuses' => Reparation::STATUSES,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'flag' => $request->flag,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Deadline yang relevan: cek dulu saat belum diperiksa, lalu deadline proses setelahnya.
     */
    private function activeDeadlineColumn(): string
    {
        return "COALESCE(CASE WHEN status IN ('RECEIVED', 'CHECKED') THEN check_deadline_at END, process_deadline_at)";
    }

    public function store(StoreReparationRequest $request, ReparationTracker $tracker): RedirectResponse
    {
        $data = $this->withTextDefaults($request->validated());

        $reparation = Reparation::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'RECEIVED',
        ]);

        $tracker->record($reparation, $request->user(), ReparationLog::EVENT_RECEIVED, to: 'RECEIVED');

        return back()->with('success', 'Servis berhasil ditambahkan.');
    }

    public function show(Reparation $reparation): Response
    {
        $reparation->load(['customer:id,name,phone', 'stages.items', 'logs.user:id,name']);

        return inertia('Servis/Detail', [
            'reparation' => (new ReparationsResource($reparation))->resolve(),
            'statuses' => Reparation::STATUSES,
        ]);
    }

    public function update(UpdateReparationRequest $request, Reparation $reparation, ReparationTracker $tracker): RedirectResponse
    {
        $data = $this->withTextDefaults($request->validated());
        $previousStatus = $reparation->status;
        // Status wajib lewat tracker supaya total & kas servis ikut tercatat.
        $data['status'] ??= $previousStatus;

        $reparation->update(Arr::except($data, 'status'));

        if ($previousStatus !== $data['status']) {
            $tracker->changeStatus($reparation, $request->user(), $data['status']);
        }

        return back()->with('success', 'Servis berhasil diperbarui.');
    }

    public function addStage(AddStageRequest $request, Reparation $reparation, ReparationTracker $tracker): RedirectResponse
    {
        $data = $request->validated();
        $lastStage = $reparation->stages()->latest('sequence')->first();
        $nextSequence = $lastStage?->sequence + 1 ?? 1;

        $stage = ReparationStage::create([
            'reparation_id' => $reparation->id,
            'sequence' => $nextSequence,
            'status' => 'PENDING',
        ]);

        foreach ($data['items'] as $item) {
            $stage->items()->create([
                'label' => $item['label'],
                'cost' => $item['cost'],
                'est_days' => $item['est_days'] ?? null,
            ]);
        }

        return back()->with('success', "Tahap {$nextSequence} ditambahkan.");
    }

    public function confirm(ConfirmReparationRequest $request, Reparation $reparation, ReparationTracker $tracker): RedirectResponse
    {
        $data = $request->validated();
        $data['process_deadline_at'] = Carbon::parse($data['process_deadline_at']);

        try {
            DB::transaction(function () use ($reparation, $data, $request, $tracker) {
                $stage = $this->resolveStageForConfirmation($reparation, $data['stage_id'] ?? null);

                $stage->items()->delete();
                foreach ($data['items'] as $item) {
                    $stage->items()->create([
                        'label' => $item['label'],
                        'cost' => $item['cost'],
                        'est_days' => $item['est_days'] ?? null,
                    ]);
                }
                $stage->update([
                    'status' => 'CONFIRMED',
                    'confirmed_at' => now(),
                ]);

                $previousStatus = $reparation->status;

                $reparation->update([
                    'process_deadline_at' => $data['process_deadline_at'],
                    'status' => 'WAITING CONFIRMATION',
                ]);

                $tracker->record(
                    $reparation,
                    $request->user(),
                    ReparationLog::EVENT_STATUS_CHANGED,
                    from: $previousStatus,
                    to: 'WAITING CONFIRMATION',
                    note: $data['note'] ?? null,
                );
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Konfirmasi gagal disimpan: '.$exception->getMessage());
        }

        return back()->with('success', 'Servis dikonfirmasi, menunggu konfirmasi customer.');
    }

    private function resolveStageForConfirmation(Reparation $reparation, ?int $stageId): ReparationStage
    {
        if ($stageId) {
            return $reparation->stages()->findOrFail($stageId);
        }

        return $reparation->stages()->latest('sequence')->first()
            ?? $reparation->stages()->create(['sequence' => 1, 'status' => 'PENDING']);
    }

    public function changeStatus(ChangeReparationStatusRequest $request, Reparation $reparation, ReparationTracker $tracker): RedirectResponse
    {
        $paymentStatus = $request->validated('payment_status');

        $tracker->changeStatus(
            $reparation,
            $request->user(),
            $request->validated('status'),
            $request->validated('note'),
            $paymentStatus === null ? null : $paymentStatus === 'paid',
        );

        return back()->with('success', 'Status servis diperbarui.');
    }

    public function markPaid(Request $request, Reparation $reparation): RedirectResponse
    {
        if ($reparation->isPaid()) {
            return back()->with('error', 'Servis ini sudah lunas.');
        }

        if (! in_array($reparation->status, ['DONE', 'TAKEN'], true)) {
            return back()->with('error', 'Servis belum selesai, belum bisa dilunasi.');
        }

        $recorded = ReparationPayment::settle($reparation, true, $request->user());

        return back()->with('success', $recorded
            ? 'Pembayaran servis dicatat ke kas.'
            : 'Pembayaran ditandai lunas (tidak ada biaya yang dicatat ke kas).');
    }

    public function destroy(Reparation $reparation): RedirectResponse
    {
        try {
            $reparation->delete();
        } catch (QueryException) {
            return back()->with('error', 'Servis tidak dapat dihapus karena sudah memiliki riwayat.');
        }

        return back()->with('success', 'Servis berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withTextDefaults(array $data): array
    {
        $data['technician_notes'] ??= '';
        $data['additional_notes'] ??= '';

        return $data;
    }
}
