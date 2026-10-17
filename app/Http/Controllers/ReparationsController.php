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
use App\Support\ReparationTracker;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class ReparationsController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $from  = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to    = $request->filled('to')   ? $request->string('to')->toString()   : $today;

        $reparations = Reparation::query()
            ->with('customer:id,name,phone')
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('item_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('flag'), fn ($query) => $query->whereJsonContains('flag', $request->string('flag')->toString()))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
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
                'flag'   => $request->flag,
                'from'   => $from,
                'to'     => $to,
            ],
        ]);
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

        $reparation->update($data);

        if ($previousStatus !== $data['status']) {
            $tracker->record($reparation, $request->user(), ReparationLog::EVENT_STATUS_CHANGED, from: $previousStatus, to: $data['status']);
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

        DB::transaction(function () use ($reparation, $data, $request, $tracker) {
            $stage = ReparationStage::find($data['stage_id']);

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

            $reparation->update([
                'process_deadline_at' => $data['process_deadline_at'],
                'status' => 'WAITING CONFIRMATION',
            ]);

            $tracker->record(
                $reparation,
                $request->user(),
                ReparationLog::EVENT_STATUS_CHANGED,
                from: $reparation->status,
                to: 'WAITING CONFIRMATION'
            );
        });

        return back()->with('success', 'Servis dikonfirmasi, menunggu konfirmasi customer.');
    }

    public function changeStatus(ChangeReparationStatusRequest $request, Reparation $reparation, ReparationTracker $tracker): RedirectResponse
    {
        $tracker->changeStatus($reparation, $request->user(), $request->validated('status'), $request->validated('note'));

        return back()->with('success', 'Status servis diperbarui.');
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
