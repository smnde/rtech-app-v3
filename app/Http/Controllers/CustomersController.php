<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Support\LikePattern;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CustomersController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $pattern = LikePattern::contains($search);
                $query->where('name', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern);
            }))
            ->when($request->filled('member'), fn ($query) => $query->where('is_member', $request->boolean('member')))
            ->when($request->filled('note_type'), fn ($query) => $query->whereHas(
                'notes',
                fn ($query) => $query->where('type', $request->note_type)->where('status', 'pending')
            ), fn ($query) => $query->when(
                $request->boolean('has_notes'),
                fn ($query) => $query->whereHas('notes', fn ($query) => $query->where('status', 'pending'))
            ))
            ->withCount([
                'notes as notes_count' => fn ($query) => $query->where('status', 'pending'),
                'notes as hutang_count' => fn ($query) => $query->where('status', 'pending')->where('type', 'hutang'),
                'notes as janji_count' => fn ($query) => $query->where('status', 'pending')->where('type', 'janji'),
                'notes as info_count' => fn ($query) => $query->where('status', 'pending')->where('type', 'info'),
                'notes as general_count' => fn ($query) => $query->where('status', 'pending')->where('type', 'general'),
            ])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'is_member' => (bool) $customer->is_member,
                'notes_count' => (int) $customer->notes_count,
                'note_counts' => [
                    'hutang' => (int) $customer->hutang_count,
                    'janji' => (int) $customer->janji_count,
                    'info' => (int) $customer->info_count,
                    'general' => (int) $customer->general_count,
                ],
            ]);

        return inertia('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->search,
                'member' => $request->member,
                'has_notes' => $request->boolean('has_notes'),
                'note_type' => $request->note_type,
            ],
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['notes' => fn ($query) => $query->latest()]);

        $transactions = collect()
            ->merge($customer->sales()->latest()->get()->map(fn ($sale) => [
                'type' => 'Penjualan',
                'reference' => $sale->invoice,
                'href' => route('transaksi.penjualan.show', $sale),
                'total' => (float) $sale->total,
                'status' => $sale->payment_status,
                'date' => $sale->created_at->toDateTimeString(),
            ]))
            ->merge($customer->purchases()->latest()->get()->map(fn ($purchase) => [
                'type' => 'Pembelian',
                'reference' => $purchase->invoice,
                'href' => route('transaksi.pembelian.show', $purchase),
                'total' => (float) $purchase->total,
                'status' => $purchase->payment_status,
                'date' => $purchase->created_at->toDateTimeString(),
            ]))
            ->sortByDesc('date')
            ->values();

        return inertia('Customers/Detail', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'is_member' => (bool) $customer->is_member,
            ],
            'notes' => $customer->notes->map(fn ($note) => [
                'id' => $note->id,
                'type' => $note->type,
                'status' => $note->status,
                'description' => $note->description,
                'created_at' => $note->created_at->toDateTimeString(),
            ])->values(),
            'transactions' => $transactions,
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return back()->with('success', 'Customer berhasil ditambahkan.')->with('customer', [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'is_member' => $customer->is_member,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('success', 'Customer berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        try {
            $customer->delete();
        } catch (QueryException) {
            return back()->with('error', 'Customer tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        return back()->with('success', 'Customer berhasil dihapus.');
    }
}
