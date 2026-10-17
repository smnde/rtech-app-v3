<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    // Kategori yang tersedia
    public const CATEGORIES = [
        'SALDO_AWAL' => 'Saldo Awal',
        'SETOR'      => 'Setor Kas',
        'TARIK'      => 'Tarik Kas',
        'KOREKSI'    => 'Koreksi',
        'LAIN'       => 'Lain-lain',
    ];

    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $from  = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to    = $request->filled('to')   ? $request->string('to')->toString()   : $today;

        $flows = CashFlow::query()
            ->with('user:id,name')
            ->whereDate('flow_date', '>=', $from)
            ->whereDate('flow_date', '<=', $to)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByDesc('flow_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Summary: semua waktu (untuk saldo total)
        $cashIn      = (float) CashFlow::where('type', 'in')->sum('amount');
        $cashOut     = (float) CashFlow::where('type', 'out')->sum('amount');
        $salesTotal  = (float) Sale::sum('total');
        $purchTotal  = (float) Purchase::sum('total');
        $expTotal    = (float) Expense::sum('amount');

        $balance = $cashIn - $cashOut + $salesTotal - $purchTotal - $expTotal;

        // Summary untuk periode yang ditampilkan
        $periodIn  = (float) CashFlow::query()
            ->where('type', 'in')
            ->whereDate('flow_date', '>=', $from)
            ->whereDate('flow_date', '<=', $to)
            ->sum('amount');
        $periodOut = (float) CashFlow::query()
            ->where('type', 'out')
            ->whereDate('flow_date', '>=', $from)
            ->whereDate('flow_date', '<=', $to)
            ->sum('amount');

        return Inertia::render('CashFlow/Index', [
            'flows' => $flows->through(fn (CashFlow $flow) => [
                'id'          => $flow->id,
                'type'        => $flow->type,
                'category'    => $flow->category,
                'description' => $flow->description,
                'amount'      => (float) $flow->amount,
                'flow_date'   => $flow->flow_date->toDateString(),
                'user_name'   => $flow->user?->name,
            ]),
            'summary' => [
                'balance'    => $balance,
                'period_in'  => $periodIn,
                'period_out' => $periodOut,
            ],
            'categories' => self::CATEGORIES,
            'filters' => [
                'from' => $from,
                'to'   => $to,
                'type' => $request->input('type', ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'        => ['required', 'in:in,out'],
            'category'    => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'flow_date'   => ['required', 'date'],
        ]);

        CashFlow::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Mutasi kas berhasil dicatat.');
    }

    public function update(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        $data = $request->validate([
            'type'        => ['required', 'in:in,out'],
            'category'    => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'flow_date'   => ['required', 'date'],
        ]);

        $cashFlow->update($data);

        return back()->with('success', 'Mutasi kas berhasil diperbarui.');
    }

    public function destroy(CashFlow $cashFlow): RedirectResponse
    {
        $cashFlow->delete();

        return back()->with('success', 'Mutasi kas berhasil dihapus.');
    }
}
