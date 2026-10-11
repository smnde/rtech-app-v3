<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SuppliersController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'description' => $supplier->description,
                'created_at' => $supplier->created_at?->toDateString(),
            ]);

        return inertia('Master/Supplier', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $request->search],
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return back()->with('success', 'Supplier berhasil ditambahkan.')->with('supplier', [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'phone' => $supplier->phone ?? '',
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        try {
            $supplier->delete();
        } catch (QueryException) {
            return back()->with('error', 'Supplier tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        return back()->with('success', 'Supplier berhasil dihapus.');
    }
}
