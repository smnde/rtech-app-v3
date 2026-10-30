<?php

namespace App\Http\Controllers;

use App\Http\Requests\Laptop\LaptopRequest;
use App\Http\Resources\LaptopsResource;
use App\Models\Brand;
use App\Models\ItemLog;
use App\Models\Laptop;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ItemImage;
use App\Support\StockHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Response;

class LaptopsController extends Controller
{
    /**
     * Urutan status mengikuti enum kolom `status` di tabel laptops.
     *
     * @var list<string>
     */
    private const STATUS_ORDER = ['ON PROSES', 'SIAP JUAL', 'DISPLAY', 'PENDING', 'KENDALA', 'HABIS'];

    /**
     * Opsi filter laporan lokal. SIAP JUAL & DISPLAY digabung jadi satu.
     *
     * @var array<string, array{statuses: list<string>, label: string}>
     */
    private const LOKAL_STATUS_FILTERS = [
        'jual_display' => ['statuses' => ['SIAP JUAL', 'DISPLAY'], 'label' => 'SIAP JUAL & DISPLAY'],
        'on_process' => ['statuses' => ['ON PROSES'], 'label' => 'ON PROSES'],
        'pending' => ['statuses' => ['PENDING'], 'label' => 'PENDING'],
        'kendala' => ['statuses' => ['KENDALA'], 'label' => 'KENDALA'],
        'habis' => ['statuses' => ['HABIS'], 'label' => 'HABIS'],
    ];

    public function index(Request $request): Response
    {
        $laptops = Laptop::query()
            ->with(['brand', 'supplier'])
            ->filterFromRequest($request)
            ->paginate(10)
            ->withQueryString();

        return inertia('Stock/Laptop', [
            'laptops' => $laptops->through(fn ($item) => (new LaptopsResource($item))->resolve()),
            'filters' => [
                'search' => $request->search,
                'brand_id' => $request->brand_id,
                'supplier_id' => $request->supplier_id,
                'status' => $request->status,
                'sort' => Laptop::sortFromRequest($request),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function show(Laptop $laptop): Response
    {
        $laptop->load(['brand', 'supplier', 'itemLogs.user:id,name', 'itemLogs.assignee:id,name']);

        return inertia('Stock/Detail', [
            'module' => 'laptop',
            'backRoute' => 'stock.laptop',
            'item' => [
                'id' => $laptop->id,
                'name' => $laptop->name,
                'sku' => $laptop->sku,
                'brand_id' => $laptop->brand_id,
                'supplier_id' => $laptop->supplier_id,
                'stock' => $laptop->stock,
                'buy_price' => $laptop->buy_price,
                'sell_price' => $laptop->sell_price,
                'notes' => $laptop->notes,
                'brand_name' => $laptop->brand?->name,
                'supplier_name' => $laptop->supplier?->name,
                'serialnumber' => $laptop->serialnumber,
                'warranty' => $laptop->warranty,
                'code' => $laptop->code,
                'image' => ItemImage::url($laptop->image),
                'specification' => $laptop->specification,
                'accessories' => $laptop->accessories ?? [],
                'features' => $laptop->features ?? [],
                'issue_notes' => $laptop->issues(),
                'status' => $laptop->status,
            ],
            'history' => StockHistory::forItem($laptop),
            'logs' => $laptop->itemLogs->map(fn (ItemLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'description' => $log->description,
                'spec_before' => $log->spec_before,
                'spec_after' => $log->spec_after,
                'status_before' => $log->status_before,
                'status_after' => $log->status_after,
                'user_id' => $log->user_id,
                'user' => ['id' => $log->user?->id, 'name' => $log->user?->name],
                'assignee' => $log->assignee ? ['id' => $log->assignee->id, 'name' => $log->assignee->name] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function store(LaptopRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['issue_notes'] = Laptop::issuesFromTexts($data['issue_notes'] ?? []);
        $data['image'] = ItemImage::store($request->file('image'));
        unset($data['remove_image']);

        $laptop = Laptop::create($data);

        return back()->with('success', 'Laptop berhasil ditambahkan.')->with('created_item', [
            'item_type' => 'laptop',
            'id' => $laptop->id,
            'label' => $laptop->name,
            'code' => $laptop->sku,
            'buy_price' => (float) $laptop->buy_price,
            'sell_price' => (float) $laptop->sell_price,
        ]);
    }

    public function update(LaptopRequest $request, Laptop $laptop): RedirectResponse
    {
        $data = collect($request->validated())->except(['stock', 'image', 'remove_image'])->all();

        if (array_key_exists('issue_notes', $data)) {
            $data['issue_notes'] = Laptop::issuesFromTexts($data['issue_notes'] ?? [], $laptop->issues());
        }

        if ($request->boolean('remove_image')) {
            ItemImage::delete($laptop->image);
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $data['image'] = ItemImage::store($request->file('image'), $laptop->image);
        }

        $laptop->update($data);

        return back()->with('success', 'Laptop berhasil diperbarui.');
    }

    public function updatePrice(Request $request, Laptop $laptop): RedirectResponse
    {
        $data = $request->validate([
            'sell_price' => ['required', 'numeric', 'min:1'],
        ]);

        $laptop->update(['sell_price' => $data['sell_price']]);

        return back()->with('success', 'Harga jual berhasil disimpan.');
    }

    public function markPosted(Laptop $laptop): RedirectResponse
    {
        if ($laptop->status !== 'SIAP JUAL' || $laptop->sell_price <= 0) {
            return back()->with('error', 'Laptop belum siap untuk di-post.');
        }

        $laptop->update([
            'is_posted' => true,
            'status' => 'DISPLAY',
        ]);

        return back()->with('success', 'Laptop berhasil di-post (status: DISPLAY).');
    }

    /**
     * Export laporan laptop ke PDF.
     *
     * - lokal: flat, dikelompokkan per status (SIAP JUAL & DISPLAY digabung).
     * - supplier: dikelompokkan per kode (pengganti unique_code di versi lama),
     *   dengan filter stok habis / masih ada.
     */
    public function exportReport(Request $request): HttpResponse
    {
        $jenis = $request->input('jenis') === 'supplier' ? 'supplier' : 'lokal';

        $data = $jenis === 'supplier'
            ? $this->supplierReport($request)
            : $this->lokalReport($request);

        $view = $jenis === 'supplier' ? 'reports.items-supplier' : 'reports.items';

        $label = $jenis === 'supplier' ? 'Supplier' : 'Lokal';
        $filename = 'Laporan-Laptop-'.$label.'-'.now('Asia/Jakarta')->format('d-m-Y').'.pdf';

        return Pdf::loadView($view, $data)->setPaper('a4', 'portrait')->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function lokalReport(Request $request): array
    {
        $filter = $request->input('status', 'jual_display');
        $conf = self::LOKAL_STATUS_FILTERS[$filter] ?? self::LOKAL_STATUS_FILTERS['jual_display'];

        $laptops = Laptop::query()
            ->whereNull('code')
            ->whereNull('supplier_id')
            ->whereIn('status', $conf['statuses'])
            ->get()
            ->sortBy(fn (Laptop $laptop) => array_search($laptop->status, self::STATUS_ORDER, true).' '.$laptop->name)
            ->values();

        $items = $laptops
            ->map(fn (Laptop $laptop, int $index) => [
                'no' => $index + 1,
                'name' => $laptop->name,
                'qty' => (int) $laptop->stock,
                'spec' => $this->specificationText($laptop->specification),
                'price' => (int) ($laptop->sell_price ?? 0),
                'status' => $laptop->status,
                'kendala' => $this->issueText($laptop->issues()),
            ])
            ->all();

        return [
            'jenis' => 'lokal',
            'items' => $items,
            'total_unit' => $laptops->sum(fn (Laptop $laptop) => (int) $laptop->stock),
            'status_label' => $conf['label'],
            'periode_label' => '',
            'show_kendala' => $filter === 'kendala',
            'empty' => $laptops->isEmpty(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierReport(Request $request): array
    {
        $stockFilter = $request->input('stock', 'all');
        $stockFilter = in_array($stockFilter, ['all', 'available', 'empty'], true) ? $stockFilter : 'all';

        $laptops = Laptop::query()
            ->with('supplier')
            ->whereNotNull('supplier_id')
            ->whereNotNull('code')
            ->when($stockFilter === 'available', fn ($query) => $query->where('stock', '>', 0))
            ->when($stockFilter === 'empty', fn ($query) => $query->where('stock', '<=', 0))
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $movements = StockMovement::query()
            ->where('item_type', (new Laptop)->getMorphClass())
            ->whereIn('item_id', $laptops->pluck('id'))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get()
            ->groupBy('item_id');

        $groups = [];
        foreach ($laptops->groupBy('code') as $code => $itemsInGroup) {
            $items = [];
            foreach ($itemsInGroup->values() as $index => $laptop) {
                $items[] = [
                    'no' => $index + 1,
                    'name' => $laptop->name,
                    'spec' => $this->specificationText($laptop->specification, $laptop->features),
                    'sell_price' => (int) ($laptop->sell_price ?? 0),
                    'stock' => (int) $laptop->stock,
                    'kendala' => $this->issueText($laptop->issues()),
                    'batches' => $this->buildBatches($laptop, $movements->get($laptop->id, collect())),
                ];
            }

            $groups[] = [
                'code' => $code,
                'supplier_name' => $itemsInGroup->first()->supplier?->name ?? '-',
                'items' => $items,
            ];
        }

        $stockLabels = [
            'all' => 'Semua (termasuk stok 0)',
            'available' => 'Hanya yang masih ada stok',
            'empty' => 'Hanya yang stok habis',
        ];

        return [
            'jenis' => 'supplier',
            'groups' => $groups,
            'periode_label' => '',
            'stock_label' => $stockLabels[$stockFilter],
            'empty' => $laptops->isEmpty(),
        ];
    }

    /**
     * Spec ringkas: value tiap field tanpa key, dipisah ' · '.
     *
     * @param  array<string, string|null>|null  $specification
     * @param  array<int, string>|null  $features
     */
    private function specificationText(?array $specification, ?array $features = null): string
    {
        $values = collect(['cpu', 'gpu', 'memory', 'storage', 'display'])
            ->map(fn (string $key) => trim((string) ($specification[$key] ?? '')))
            ->filter(fn (string $value) => $value !== '');

        foreach ($features ?? [] as $feature) {
            $value = trim((string) $feature);
            if ($value !== '') {
                $values->push($value);
            }
        }

        return $values->isNotEmpty() ? $values->implode(' · ') : '-';
    }

    /**
     * Kendala aktif (belum selesai) dipisah ' · '.
     *
     * @param  list<array{id: string, text: string, solved: bool, solved_at: ?string}>  $issues
     */
    private function issueText(array $issues): string
    {
        $texts = collect($issues)
            ->reject(fn (array $issue) => (bool) ($issue['solved'] ?? false))
            ->map(fn (array $issue) => trim((string) ($issue['text'] ?? '')))
            ->filter(fn (string $text) => $text !== '');

        return $texts->isNotEmpty() ? $texts->implode(' · ') : '-';
    }

    /**
     * Batch per item: tiap `in` = 1 batch, `out` dialokasikan FIFO.
     *
     * @param  Collection<int, StockMovement>  $movements  urut dari terlama
     * @return list<array{tgl_masuk: string, qty: int, remaining: int, outs: list<array{tgl_keluar: string, qty: int}>}>
     */
    private function buildBatches(Laptop $laptop, Collection $movements): array
    {
        $formatDate = fn (StockMovement|Carbon $date) => Carbon::parse($date instanceof StockMovement ? ($date->movement_date ?? $date->created_at) : $date)
            ->timezone('Asia/Jakarta')
            ->format('d M Y');

        $ins = $movements->where('type', 'in')->values();
        $outs = $movements->where('type', 'out')->values();

        $batches = [];
        foreach ($ins as $movement) {
            $batches[] = [
                'tgl_masuk' => $formatDate($movement),
                'qty' => (int) $movement->quantity,
                'remaining' => (int) $movement->quantity,
                'outs' => [],
            ];
        }

        $index = 0;
        foreach ($outs as $out) {
            $toConsume = (int) $out->quantity;
            while ($toConsume > 0) {
                while ($index < count($batches) && $batches[$index]['remaining'] <= 0) {
                    $index++;
                }
                if ($index >= count($batches)) {
                    break;
                }

                $consume = min($toConsume, $batches[$index]['remaining']);
                $batches[$index]['outs'][] = [
                    'tgl_keluar' => $formatDate($out),
                    'qty' => $consume,
                ];
                $batches[$index]['remaining'] -= $consume;
                $toConsume -= $consume;

                if ($batches[$index]['remaining'] <= 0) {
                    $index++;
                }
            }
        }

        if ($batches === [] && $outs->isNotEmpty()) {
            $start = (int) $outs->first()->stock_before;
            $batches[] = [
                'tgl_masuk' => $formatDate($laptop->created_at),
                'qty' => $start,
                'remaining' => max($start - (int) $outs->sum('quantity'), 0),
                'outs' => $outs->map(fn (StockMovement $out) => [
                    'tgl_keluar' => $formatDate($out),
                    'qty' => (int) $out->quantity,
                ])->all(),
            ];
        }

        if ($batches === []) {
            $batches[] = [
                'tgl_masuk' => Carbon::parse($laptop->created_at)->timezone('Asia/Jakarta')->format('d M Y'),
                'qty' => (int) $laptop->stock,
                'remaining' => (int) $laptop->stock,
                'outs' => [],
            ];
        }

        return $batches;
    }

    public function destroy(Laptop $laptop): RedirectResponse
    {
        try {
            $laptop->delete();
        } catch (QueryException) {
            return back()->with('error', 'Laptop tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        ItemImage::delete($laptop->image);

        return back()->with('success', 'Laptop berhasil dihapus.');
    }
}
