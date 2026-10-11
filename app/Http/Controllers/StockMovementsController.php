<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Support\StockItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementsController extends Controller
{
    public function index(Request $request): Response
    {
        $movements = StockMovement::query()
            ->with(['item', 'user:id,name'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('to')))
            ->when($request->filled('type') && $request->string('type') !== 'all', fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status') && $request->string('status') !== 'all', fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('StockMovements/Index', [
            'movements' => $movements->through(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'date' => $movement->created_at->toDateString(),
                'item_name' => $movement->item?->name ?? '-',
                'item_type' => $movement->item_type,
                'type' => $movement->type,
                'status' => $movement->status,
                'quantity' => $movement->quantity,
                'stock_before' => $movement->stock_before,
                'stock_after' => $movement->stock_after,
                'reference' => $movement->reference,
                'user_name' => $movement->user?->name,
            ]),
            'items' => collect(StockItem::types())->flatMap(
                fn (string $modelClass, string $type) => $modelClass::query()
                    ->select('id', 'name', 'sku', 'stock')
                    ->get()
                    ->map(fn (Model $item) => [
                        'key' => "{$type}:{$item->id}",
                        'item_type' => $type,
                        'item_id' => $item->id,
                        'label' => "{$item->name} ({$item->sku})",
                        'stock' => $item->stock,
                    ]),
            )->values(),
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'type' => $request->input('type', 'all'),
                'status' => $request->input('status', 'all'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_type' => ['required', 'string', 'in:'.implode(',', array_keys(StockItem::types()))],
            'item_id' => ['required', 'integer'],
            'type' => ['required', 'in:in,out,adjustment'],
            'status' => ['required_unless:type,adjustment', 'nullable', 'in:NONE,BONUS,DEBT,SOLD OUT,REFUND'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $stockItem = StockItem::modelFor($data['item_type'])::query()
                ->lockForUpdate()
                ->findOrFail($data['item_id']);

            $stockBefore = $stockItem->stock;
            $stockAfter = match ($data['type']) {
                'in' => $stockBefore + $data['quantity'],
                'out' => $stockBefore - $data['quantity'],
                'adjustment' => $data['quantity'],
            };

            if ($stockAfter < 0) {
                abort(422, 'Stok tidak mencukupi.');
            }

            $stockItem->update(['stock' => $stockAfter]);

            StockMovement::create([
                'item_type' => $stockItem->getMorphClass(),
                'item_id' => $stockItem->id,
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'status' => $data['type'] === 'adjustment' ? 'NONE' : $data['status'],
                'quantity' => $data['quantity'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Mutasi stok berhasil dicatat.');
    }
}
