<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brand\BrandRequest;
use App\Models\Brand;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BrandsController extends Controller
{
    public function index(Request $request): Response
    {
        $brands = Brand::query()
            ->when($request->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'created_at' => $brand->created_at?->toDateString(),
            ]);

        return inertia('Master/Brand', [
            'brands' => $brands,
            'filters' => ['search' => $request->search],
        ]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Brand::create($request->validated());

        return back()->with('success', 'Brand berhasil ditambahkan.');
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated());

        return back()->with('success', 'Brand berhasil diperbarui.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        try {
            $brand->delete();
        } catch (QueryException) {
            return back()->with('error', 'Brand tidak dapat dihapus karena masih dipakai data produk.');
        }

        return back()->with('success', 'Brand berhasil dihapus.');
    }
}
