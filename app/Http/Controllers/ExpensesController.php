<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends Controller
{
    public function index(Request $request): Response
    {
        $expenses = Expense::query()
            ->with('user:id,name')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('to')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses->through(fn (Expense $expense) => [
                'id' => $expense->id,
                'date' => $expense->created_at->toDateTimeString(),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'user_name' => $expense->user?->name,
            ]),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'user_id' => $request->input('user_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        Expense::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $expense->update($data);

        return back()->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Pengeluaran berhasil dihapus.');
    }
}
