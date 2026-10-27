<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Support\StockItem;
use App\Support\StockMovementRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementsController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $from = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to = $request->filled('to') ? $request->string('to')->toString() : $today;

        $movements = StockMovement::query()
            ->with(['item', 'user:id,name'])
            ->where('movement_date', '>=', $from)
            ->where('movement_date', '<=', $to)
            ->when($request->filled('type') && $request->string('type') !== 'all', fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status') && $request->string('status') !== 'all', fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('StockMovements/Index', [
            'movements' => $movements->through(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'date' => $movement->movement_date?->toDateString(),
                'item_name' => $movement->item?->name ?? '-',
                'item_type' => $movement->item_type,
                'type' => $movement->type,
                'status' => $movement->status,
                'quantity' => $movement->quantity,
                'stock_before' => $movement->stock_before,
                'stock_after' => $movement->stock_after,
                'reference' => $movement->reference,
                'notes' => $movement->notes,
                'user_name' => $movement->user?->name,
            ]),
            'items' => collect(StockItem::types())->flatMap(
                fn (string $modelClass, string $type) => $modelClass::query()
                    ->select(array_values(array_filter(['id', 'name', 'sku', 'stock', $type === 'laptop' ? 'code' : null])))
                    ->get()
                    ->map(fn (Model $item) => [
                        'key' => "{$type}:{$item->id}",
                        'item_type' => $type,
                        'item_id' => $item->id,
                        'label' => "{$item->name} ({$item->sku})",
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'code' => $type === 'laptop' ? $item->code : null,
                        'stock' => $item->stock,
                    ]),
            )->values(),
            'filters' => [
                'from' => $from,
                'to' => $to,
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
            'status' => ['required', 'in:PEMBELIAN,REFUND,TERJUAL,BONUS,HUTANG,PENYESUAIAN'],
            'movement_date' => ['required', 'date'],
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
                'status' => $data['type'] === 'adjustment' ? 'PENYESUAIAN' : $data['status'],
                'movement_date' => $data['movement_date'],
                'quantity' => $data['quantity'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            StockMovementRecorder::syncStatus($stockItem);
        });

        return back()->with('success', 'Mutasi stok berhasil dicatat.');
    }

    public function update(Request $request, StockMovement $stockMovement): RedirectResponse
    {
        $data = $request->validate([
            'movement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $stockMovement->update([
            'movement_date' => $data['movement_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Mutasi stok berhasil diperbarui.');
    }

    public function destroy(StockMovement $stockMovement): RedirectResponse
    {
        DB::transaction(function () use ($stockMovement) {
            $stockItem = StockItem::modelFor($stockMovement->item_type)::query()
                ->lockForUpdate()
                ->findOrFail($stockMovement->item_id);
            $reversedStock = $stockItem->stock - ($stockMovement->stock_after - $stockMovement->stock_before);

            if ($reversedStock < 0) {
                throw ValidationException::withMessages([
                    'stock' => 'Mutasi tidak bisa dihapus karena stok item akan menjadi negatif.',
                ]);
            }

            $stockItem->update(['stock' => $reversedStock]);
            $stockMovement->delete();

            StockMovementRecorder::syncStatus($stockItem->refresh());
        });

        return back()->with('success', 'Mutasi stok dihapus dan stok dikembalikan.');
    }
}
