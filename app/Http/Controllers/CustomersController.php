<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
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
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($request->filled('member'), fn ($query) => $query->where('is_member', $request->boolean('member')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'is_member' => (bool) $customer->is_member,
            ]);

        return inertia('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $request->search, 'member' => $request->member],
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
