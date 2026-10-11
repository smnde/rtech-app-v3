<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
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
            ->get();

        $needPost = Laptop::query()
            ->where('status', 'SIAP JUAL')
            ->where('sell_price', '>', 0)
            ->where('is_posted', false)
            ->with('supplier:id,name')
            ->select(self::LAPTOP_DETAIL_COLUMNS)
            ->latest('updated_at')
            ->get();

        $today = now()->toDateString();

        $salesTodayTotal = (float) Sale::query()->whereDate('created_at', $today)->sum('total');
        $purchasesTodayTotal = (float) Purchase::query()->whereDate('created_at', $today)->sum('total');
        $expensesTodayTotal = (float) Expense::query()->whereDate('created_at', $today)->sum('amount');

        $totalIncome = (float) Sale::query()->sum('total');
        $totalOutcome = (float) Purchase::query()->sum('total') + (float) Expense::query()->sum('amount');

        return Inertia::render('Dashboard', [
            'stats' => [
                'sales_count_today' => Sale::query()->whereDate('created_at', $today)->count(),
                'purchases_count_today' => Purchase::query()->whereDate('created_at', $today)->count(),
                'income_today' => $salesTodayTotal,
                'outcome_today' => $purchasesTodayTotal + $expensesTodayTotal,
                'balance' => $totalIncome - $totalOutcome,
                'customers' => Customer::count(),
                'servis' => Reparation::whereNotIn('status', ['DONE', 'TAKEN', 'CANCEL'])->count(),
                'items' => (int) (Laptop::sum('stock') + Gadget::sum('stock') + Accessory::sum('stock') + Sparepart::sum('stock')),
            ],
            'needPrice' => $needPrice,
            'needPost' => $needPost,
        ]);
    }
}
