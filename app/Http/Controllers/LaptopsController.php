<?php

namespace App\Http\Controllers;

use App\Http\Requests\Laptop\LaptopRequest;
use App\Http\Resources\LaptopsResource;
use App\Models\Brand;
use App\Models\ItemLog;
use App\Models\Laptop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ItemImage;
use App\Support\StockHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class LaptopsController extends Controller
{
    public function index(Request $request): Response
    {
        $laptops = Laptop::query()
            ->with(['brand', 'supplier'])
            ->filterFromRequest($request)
            ->paginate(10)
            ->withQueryString();

        return inertia('Stock/Laptop', [
            'laptops' => $laptops->through(fn ($item) => (new LaptopsResource($item))->resolve()),
            'filters' => [
                'search' => $request->search,
                'brand_id' => $request->brand_id,
                'supplier_id' => $request->supplier_id,
                'status' => $request->status,
                'sort' => Laptop::sortFromRequest($request),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function show(Laptop $laptop): Response
    {
        $laptop->load(['brand', 'supplier', 'itemLogs.user:id,name', 'itemLogs.assignee:id,name']);

        return inertia('Stock/Detail', [
            'module' => 'laptop',
            'backRoute' => 'stock.laptop',
            'item' => [
                'id' => $laptop->id,
                'name' => $laptop->name,
                'sku' => $laptop->sku,
                'brand_id' => $laptop->brand_id,
                'supplier_id' => $laptop->supplier_id,
                'stock' => $laptop->stock,
                'buy_price' => $laptop->buy_price,
                'sell_price' => $laptop->sell_price,
                'notes' => $laptop->notes,
                'brand_name' => $laptop->brand?->name,
                'supplier_name' => $laptop->supplier?->name,
                'serialnumber' => $laptop->serialnumber,
                'warranty' => $laptop->warranty,
                'code' => $laptop->code,
                'image' => ItemImage::url($laptop->image),
                'specification' => $laptop->specification,
                'accessories' => $laptop->accessories ?? [],
                'features' => $laptop->features ?? [],
                'issue_notes' => $laptop->issues(),
                'status' => $laptop->status,
            ],
            'history' => StockHistory::forItem($laptop),
            'logs' => $laptop->itemLogs->map(fn (ItemLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'description' => $log->description,
                'spec_before' => $log->spec_before,
                'spec_after' => $log->spec_after,
                'status_before' => $log->status_before,
                'status_after' => $log->status_after,
                'user_id' => $log->user_id,
                'user' => ['id' => $log->user?->id, 'name' => $log->user?->name],
                'assignee' => $log->assignee ? ['id' => $log->assignee->id, 'name' => $log->assignee->name] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name as label']),
        ]);
    }

    public function store(LaptopRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['issue_notes'] = Laptop::issuesFromTexts($data['issue_notes'] ?? []);
        $data['image'] = ItemImage::store($request->file('image'));
        unset($data['remove_image']);

        $laptop = Laptop::create($data);

        return back()->with('success', 'Laptop berhasil ditambahkan.')->with('created_item', [
            'item_type' => 'laptop',
            'id' => $laptop->id,
            'label' => $laptop->name,
            'code' => $laptop->sku,
            'buy_price' => (float) $laptop->buy_price,
            'sell_price' => (float) $laptop->sell_price,
        ]);
    }

    public function update(LaptopRequest $request, Laptop $laptop): RedirectResponse
    {
        $data = collect($request->validated())->except(['stock', 'image', 'remove_image'])->all();

        if (array_key_exists('issue_notes', $data)) {
            $data['issue_notes'] = Laptop::issuesFromTexts($data['issue_notes'] ?? [], $laptop->issues());
        }

        if ($request->boolean('remove_image')) {
            ItemImage::delete($laptop->image);
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $data['image'] = ItemImage::store($request->file('image'), $laptop->image);
        }

        $laptop->update($data);

        return back()->with('success', 'Laptop berhasil diperbarui.');
    }

    public function updatePrice(Request $request, Laptop $laptop): RedirectResponse
    {
        $data = $request->validate([
            'sell_price' => ['required', 'numeric', 'min:1'],
        ]);

        $laptop->update(['sell_price' => $data['sell_price']]);

        return back()->with('success', 'Harga jual berhasil disimpan.');
    }

    public function markPosted(Laptop $laptop): RedirectResponse
    {
        if ($laptop->status !== 'SIAP JUAL' || $laptop->sell_price <= 0) {
            return back()->with('error', 'Laptop belum siap untuk di-post.');
        }

        $laptop->update([
            'is_posted' => true,
            'status' => 'DISPLAY',
        ]);

        return back()->with('success', 'Laptop berhasil di-post (status: DISPLAY).');
    }

    public function destroy(Laptop $laptop): RedirectResponse
    {
        try {
            $laptop->delete();
        } catch (QueryException) {
            return back()->with('error', 'Laptop tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        ItemImage::delete($laptop->image);

        return back()->with('success', 'Laptop berhasil dihapus.');
    }
}
