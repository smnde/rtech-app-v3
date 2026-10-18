<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Gadget;
use App\Models\Laptop;
use App\Models\Purchase;
use App\Models\Reparation;
use App\Models\Sale;
use App\Models\Sparepart;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const LAPTOP_DETAIL_COLUMNS = [
        'id', 'name', 'sku', 'code', 'supplier_id', 'warranty', 'buy_price', 'sell_price',
        'specification', 'features', 'accessories', 'issue_notes',
    ];

    public function index(): Response
    {
        $needPrice = Laptop::query()
            ->where('status', 'SIAP JUAL')
            ->where(fn ($query) => $query->whereNull('sell_price')->orWhere('sell_price', '<=', 0))
            ->with('supplier:id,name')
            ->select(self::LAPTOP_DETAIL_COLUMNS)
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $needPost = Laptop::query()
            ->where('status', 'SIAP JUAL')
            ->where('sell_price', '>', 0)
            ->where('is_posted', false)
            ->with('supplier:id,name')
            ->select(self::LAPTOP_DETAIL_COLUMNS)
            ->latest('updated_at')
            ->limit(100)
            ->get();

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
}
