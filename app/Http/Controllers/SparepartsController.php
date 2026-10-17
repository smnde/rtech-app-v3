<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sparepart\SparepartRequest;
use App\Http\Resources\SparepartsResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Sparepart;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SparepartsController extends Controller
{
    public function index(Request $request): Response
    {
        $spareparts = Sparepart::query()
            ->with(['category', 'brand', 'supplier'])
            ->filterFromRequest($request)
            ->paginate(10)
            ->withQueryString();

        return inertia('Stock/Sparepart', [
            'spareparts' => $spareparts->through(fn ($item) => (new SparepartsResource($item))->resolve()),
            'filters' => [
                'search' => $request->search,
                'brand_id' => $request->brand_id,
                'supplier_id' => $request->supplier_id,
                'category_id' => $request->category_id,
                'sort' => Sparepart::sortFromRequest($request),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function show(Sparepart $sparepart): Response
    {
        $sparepart->load(['category', 'brand', 'supplier']);

        return inertia('Stock/Detail', [
            'module' => 'sparepart',
            'backRoute' => 'stock.sparepart',
            'item' => [
                'id' => $sparepart->id,
                'name' => $sparepart->name,
                'sku' => $sparepart->sku,
                'warranty' => $sparepart->warranty,
                'stock' => $sparepart->stock,
                'buy_price' => $sparepart->buy_price,
                'sell_price' => $sparepart->sell_price,
                'status' => $sparepart->status,
                'notes' => $sparepart->notes,
                'brand_name' => $sparepart->brand?->name,
                'supplier_name' => $sparepart->supplier?->name,
                'category_name' => $sparepart->category?->name,
            ],
            'history' => [],
        ]);
    }

    public function store(SparepartRequest $request): RedirectResponse
    {
        $sparepart = Sparepart::create([...$request->validated(), 'notes' => $request->string('notes')->toString()]);

        return back()->with('success', 'Sparepart berhasil ditambahkan.')->with('created_item', [
            'item_type' => 'sparepart',
            'id' => $sparepart->id,
            'label' => $sparepart->name,
            'code' => $sparepart->sku,
            'buy_price' => (float) $sparepart->buy_price,
            'sell_price' => (float) $sparepart->sell_price,
        ]);
    }

    public function update(SparepartRequest $request, Sparepart $sparepart): RedirectResponse
    {
        $sparepart->update([...collect($request->validated())->except('stock')->all(), 'notes' => $request->string('notes')->toString()]);

        return back()->with('success', 'Sparepart berhasil diperbarui.');
    }

    public function destroy(Sparepart $sparepart): RedirectResponse
    {
        try {
            $sparepart->delete();
        } catch (QueryException) {
            return back()->with('error', 'Sparepart tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        return back()->with('success', 'Sparepart berhasil dihapus.');
    }
}
