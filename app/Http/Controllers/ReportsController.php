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
            ->with('customer:id,name')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        return Inertia::render('Laporan/Penjualan', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $sales->map(fn (Sale $sale) => [
                'invoice' => $sale->invoice,
                'date' => $sale->created_at->toDateString(),
                'customer' => $sale->customer?->name ?? '-',
                'total' => (float) $sale->total,
                'payment_status' => $sale->payment_status,
            ])->values(),
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
            ->with('customer:id,name')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        return Inertia::render('Laporan/Servis', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $reparations->map(fn (Reparation $reparation) => [
                'id' => $reparation->id,
                'date' => $reparation->created_at->toDateString(),
                'customer' => $reparation->customer?->name ?? '-',
                'item_name' => $reparation->item_name,
                'complaint' => implode(', ', $reparation->complaint ?? []),
                'estimated_cost' => $reparation->estimated_cost,
                'status' => $reparation->status,
            ])->values(),
            'summary' => [
                'count' => $reparations->count(),
                'done' => $reparations->whereIn('status', ['DONE', 'TAKEN'])->count(),
                'process' => $reparations->whereIn('status', ['ON PROCESS', 'CHECKED', 'CONFIRMED'])->count(),
                'pending' => $reparations->whereIn('status', ['RECEIVED', 'WAITING CONFIRMATION', 'PENDING'])->count(),
            ],
        ]);
    }

    public function pembelian(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'customer:id,name'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        return Inertia::render('Laporan/Pembelian', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $purchases->map(fn (Purchase $purchase) => [
                'invoice' => $purchase->invoice,
                'date' => $purchase->created_at->toDateString(),
                'source' => $purchase->supplier?->name ?? $purchase->customer?->name ?? '-',
                'total' => (float) $purchase->total,
                'payment_status' => $purchase->payment_status,
                'po_type' => $purchase->po_type,
            ])->values(),
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
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->get();

        return Inertia::render('Laporan/MutasiStok', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $movements->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'date' => $movement->created_at->toDateString(),
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
