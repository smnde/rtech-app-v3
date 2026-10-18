<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Reparation;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function sales(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $sales = Sale::query()
            ->with(['details' => fn ($q) => $q->where('qty', '>', 0)->with('item:id,name')])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        $rows = $sales
            ->flatMap(fn (Sale $sale) => $sale->details->map(fn ($detail) => [
                'invoice' => $sale->invoice,
                'sale_id' => $sale->id,
                'date' => $sale->created_at->toDateString(),
                'item_name' => $detail->item?->name ?? '-',
                'qty' => (int) $detail->qty,
                'subtotal' => (float) $detail->subtotal,
            ]))
            ->values();

        return Inertia::render('Laporan/Penjualan', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
            'summary' => [
                'count' => $sales->count(),
                'total' => (float) $sales->sum('total'),
            ],
        ]);
    }

    public function servis(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $reparations = Reparation::query()
            ->with(['stages.items', 'customer:id,name'])
            ->where('status', 'TAKEN')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        $rows = $reparations
            ->flatMap(function (Reparation $reparation) {
                $items = $reparation->stages
                    ->filter(fn ($stage) => $stage->status === 'CONFIRMED')
                    ->flatMap(fn ($stage) => $stage->items)
                    ->values();

                if ($items->isEmpty()) {
                    return [[
                        'invoice' => $reparation->invoice,
                        'id' => $reparation->id,
                        'date' => $reparation->created_at->toDateString(),
                        'customer' => $reparation->customer?->name ?? 'Umum',
                        'label' => '-',
                        'cost' => (float) (($reparation->total_price ?? 0) ?: $reparation->confirmedTotal()),
                        'paid' => $reparation->isPaid(),
                    ]];
                }

                return $items->map(fn ($item) => [
                    'invoice' => $reparation->invoice,
                    'id' => $reparation->id,
                    'date' => $reparation->created_at->toDateString(),
                    'customer' => $reparation->customer?->name ?? 'Umum',
                    'label' => $item->label,
                    'cost' => (float) $item->cost,
                    'paid' => $reparation->isPaid(),
                ]);
            })
            ->values();

        return Inertia::render('Laporan/Servis', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
            'summary' => [
                'count' => $reparations->count(),
                'total' => (float) $reparations->sum(fn (Reparation $reparation) => (float) (($reparation->total_price ?? 0) ?: $reparation->confirmedTotal())),
                'unpaid' => $reparations->where('payment_status', 'unpaid')->count(),
            ],
        ]);
    }

    public function pembelian(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $purchases = Purchase::query()
            ->with(['details' => fn ($q) => $q->where('qty', '>', 0)->with('item:id,name')])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        $rows = $purchases
            ->flatMap(fn (Purchase $purchase) => $purchase->details->map(fn ($detail) => [
                'invoice' => $purchase->invoice,
                'purchase_id' => $purchase->id,
                'date' => $purchase->created_at->toDateString(),
                'item_name' => $detail->item?->name ?? '-',
                'qty' => (int) $detail->qty,
                'subtotal' => (float) $detail->subtotal,
            ]))
            ->values();

        return Inertia::render('Laporan/Pembelian', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
            'summary' => [
                'count' => $purchases->count(),
                'total' => (float) $purchases->sum('total'),
            ],
        ]);
    }

    public function pengeluaran(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $expenses = Expense::query()
            ->with('user:id,name')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        return Inertia::render('Laporan/Pengeluaran', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $expenses->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'date' => $expense->created_at->toDateString(),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'user_name' => $expense->user?->name,
            ])->values(),
            'summary' => [
                'count' => $expenses->count(),
                'total' => (float) $expenses->sum('amount'),
            ],
        ]);
    }

    public function mutasi(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $movements = StockMovement::query()
            ->with(['item', 'user:id,name'])
            ->whereDate('movement_date', '>=', $from)
            ->whereDate('movement_date', '<=', $to)
            ->latest()
            ->get();

        return Inertia::render('Laporan/MutasiStok', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $movements->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'date' => $movement->movement_date?->toDateString(),
                'item_name' => $movement->item?->name ?? '-',
                'item_type' => $movement->item_type,
                'status' => $movement->status,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'stock_before' => $movement->stock_before,
                'stock_after' => $movement->stock_after,
                'reference' => $movement->reference,
                'user_name' => $movement->user?->name,
            ])->values(),
            'summary' => [
                'count' => $movements->count(),
                'in' => (int) $movements->where('type', 'in')->sum('quantity'),
                'out' => (int) $movements->where('type', 'out')->sum('quantity'),
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function period(Request $request): array
    {
        $today = now()->toDateString();
        $from = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to = $request->filled('to') ? $request->string('to')->toString() : $today;

        return [$from, $to];
    }
}
