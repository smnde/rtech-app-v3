<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gadget\GadgetRequest;
use App\Http\Resources\GadgetsResource;
use App\Models\Brand;
use App\Models\Gadget;
use App\Models\Supplier;
use App\Support\StockHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class GadgetsController extends Controller
{
    public function index(Request $request): Response
    {
        $gadgets = Gadget::query()
            ->with(['brand', 'supplier'])
            ->filterFromRequest($request)
            ->paginate(10)
            ->withQueryString();

        return inertia('Stock/Gadget', [
            'gadgets' => $gadgets->through(fn ($item) => (new GadgetsResource($item))->resolve()),
            'filters' => [
                'search' => $request->search,
                'brand_id' => $request->brand_id,
                'supplier_id' => $request->supplier_id,
                'status' => $request->status,
                'sort' => Gadget::sortFromRequest($request),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function show(Gadget $gadget): Response
    {
        $gadget->load(['brand', 'supplier']);

        return inertia('Stock/Detail', [
            'module' => 'gadget',
            'backRoute' => 'stock.gadget',
            'item' => [
                'id' => $gadget->id,
                'name' => $gadget->name,
                'sku' => $gadget->sku,
                'stock' => $gadget->stock,
                'buy_price' => $gadget->buy_price,
                'sell_price' => $gadget->sell_price,
                'status' => $gadget->status,
                'notes' => $gadget->notes,
                'brand_name' => $gadget->brand?->name,
                'supplier_name' => $gadget->supplier?->name,
                'serialnumber' => $gadget->serialnumber,
                'warranty' => $gadget->warranty,
                'specification' => $gadget->specification,
                'accessories' => $gadget->accessories ?? [],
                'issue_notes' => $gadget->issue_notes ?? [],
            ],
            'history' => StockHistory::forItem($gadget),
        ]);
    }

    public function store(GadgetRequest $request): RedirectResponse
    {
        $gadget = Gadget::create([...$request->validated(), 'specification' => $request->validated('specification') ?? []]);

        return back()->with('success', 'Gadget berhasil ditambahkan.')->with('created_item', [
            'item_type' => 'gadget',
            'id' => $gadget->id,
            'label' => $gadget->name,
            'code' => $gadget->sku,
            'buy_price' => (float) $gadget->buy_price,
            'sell_price' => (float) $gadget->sell_price,
        ]);
    }

    public function update(GadgetRequest $request, Gadget $gadget): RedirectResponse
    {
        $gadget->update([...collect($request->validated())->except('stock')->all(), 'specification' => $request->validated('specification') ?? []]);

        return back()->with('success', 'Gadget berhasil diperbarui.');
    }

    public function destroy(Gadget $gadget): RedirectResponse
    {
        try {
            $gadget->delete();
        } catch (QueryException) {
            return back()->with('error', 'Gadget tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        return back()->with('success', 'Gadget berhasil dihapus.');
    }
}
