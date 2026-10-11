<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accessory\AccessoryRequest;
use App\Http\Resources\AccessoriesResource;
use App\Models\Accessory;
use App\Models\Brand;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AccesoriesController extends Controller
{
    public function index(Request $request): Response
    {
        $accessories = Accessory::query()
            ->with(['brand', 'supplier'])
            ->filterFromRequest($request)
            ->paginate(10)
            ->withQueryString();

        return inertia('Stock/Aksesoris', [
            'accessories' => $accessories->through(fn ($item) => (new AccessoriesResource($item))->resolve()),
            'filters' => [
                'search' => $request->search,
                'brand_id' => $request->brand_id,
                'supplier_id' => $request->supplier_id,
                'status' => $request->status,
                'sort' => Accessory::sortFromRequest($request),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function show(Accessory $accessory): Response
    {
        $accessory->load(['brand', 'supplier']);

        return inertia('Stock/Detail', [
            'module' => 'aksesoris',
            'backRoute' => 'stock.aksesoris',
            'item' => [
                'id' => $accessory->id,
                'name' => $accessory->name,
                'sku' => $accessory->sku,
                'warranty' => $accessory->warranty,
                'stock' => $accessory->stock,
                'buy_price' => $accessory->buy_price,
                'sell_price' => $accessory->sell_price,
                'status' => $accessory->status,
                'notes' => $accessory->notes,
                'brand_name' => $accessory->brand?->name,
                'supplier_name' => $accessory->supplier?->name,
            ],
            'history' => [],
        ]);
    }

    public function store(AccessoryRequest $request): RedirectResponse
    {
        $accessory = Accessory::create($request->validated());

        return back()->with('success', 'Aksesoris berhasil ditambahkan.')->with('created_item', [
            'item_type' => 'accessory',
            'id' => $accessory->id,
            'label' => $accessory->name,
            'code' => $accessory->sku,
            'buy_price' => (float) $accessory->buy_price,
            'sell_price' => (float) $accessory->sell_price,
        ]);
    }

    public function update(AccessoryRequest $request, Accessory $accessory): RedirectResponse
    {
        $accessory->update(collect($request->validated())->except('stock')->all());

        return back()->with('success', 'Aksesoris berhasil diperbarui.');
    }

    public function destroy(Accessory $accessory): RedirectResponse
    {
        try {
            $accessory->delete();
        } catch (QueryException) {
            return back()->with('error', 'Aksesoris tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        return back()->with('success', 'Aksesoris berhasil dihapus.');
    }
}
