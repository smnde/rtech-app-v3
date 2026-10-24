<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Gadget;
use App\Models\ItemLog;
use App\Models\Laptop;
use App\Models\Purchase;
use App\Models\Reparation;
use App\Models\Sale;
use App\Models\Sparepart;
use App\Support\ItemImage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const LAPTOP_DETAIL_COLUMNS = [
        'id', 'name', 'sku', 'code', 'image', 'supplier_id', 'warranty', 'buy_price', 'sell_price',
        'specification', 'features', 'accessories', 'issue_notes',
    ];

    public function index(): Response
    {
        $needPrice = Laptop::query()
            ->where('status', 'SIAP JUAL')
            ->where(fn ($query) => $query->whereNull('sell_price')->orWhere('sell_price', '<=', 0))
            ->with(['supplier:id,name', 'itemLogs.user:id,name', 'itemLogs.assignee:id,name'])
            ->select(self::LAPTOP_DETAIL_COLUMNS)
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Laptop $laptop) => $this->dashboardItem($laptop, withLogs: true));

        $needPost = Laptop::query()
            ->where('status', 'SIAP JUAL')
            ->where('sell_price', '>', 0)
            ->where('is_posted', false)
            ->with(['supplier:id,name', 'itemLogs.user:id,name', 'itemLogs.assignee:id,name'])
            ->select(self::LAPTOP_DETAIL_COLUMNS)
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Laptop $laptop) => $this->dashboardItem($laptop, withLogs: true));

        $today = now();
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();

        // whereBetween (bukan whereDate) supaya indeks created_at tetap terpakai.
        $salesToday = Sale::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(total), 0) as total')
            ->first();

        $purchasesToday = Purchase::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(total), 0) as total')
            ->first();

        $expensesTodayTotal = (float) Expense::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $cashFlows = CashFlow::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount END), 0) as masuk")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount END), 0) as keluar")
            ->first();

        $stock = (int) (Laptop::sum('stock') + Gadget::sum('stock') + Accessory::sum('stock') + Sparepart::sum('stock'));

        $balance = (float) $cashFlows->masuk - (float) $cashFlows->keluar;

        return Inertia::render('Dashboard', [
            'stats' => [
                'sales_count_today' => (int) $salesToday->jumlah,
                'purchases_count_today' => (int) $purchasesToday->jumlah,
                'income_today' => (float) $salesToday->total,
                'outcome_today' => (float) $purchasesToday->total + $expensesTodayTotal,
                'balance' => $balance,
                'customers' => Customer::count(),
                'servis' => Reparation::whereNotIn('status', ['DONE', 'TAKEN', 'CANCEL'])->count(),
                'items' => $stock,
            ],
            'needPrice' => $needPrice,
            'needPost' => $needPost,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardItem(Laptop $laptop, bool $withLogs = false): array
    {
        $item = [
            'id' => $laptop->id,
            'name' => $laptop->name,
            'sku' => $laptop->sku,
            'code' => $laptop->code,
            'image' => ItemImage::url($laptop->image),
            'supplier' => $laptop->supplier ? ['name' => $laptop->supplier->name] : null,
            'warranty' => $laptop->warranty,
            'buy_price' => $laptop->buy_price,
            'sell_price' => $laptop->sell_price,
            'specification' => $laptop->specification,
            'features' => $laptop->features ?? [],
            'accessories' => $laptop->accessories ?? [],
            'issue_notes' => $laptop->issues(),
        ];

        if ($withLogs) {
            $item['logs'] = $laptop->itemLogs->map(fn (ItemLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'description' => $log->description,
                'spec_before' => $log->spec_before,
                'spec_after' => $log->spec_after,
                'status_before' => $log->status_before,
                'status_after' => $log->status_after,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'assignee' => $log->assignee ? ['id' => $log->assignee->id, 'name' => $log->assignee->name] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values();
        }

        return $item;
    }
}
