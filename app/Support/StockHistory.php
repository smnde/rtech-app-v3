<?php

namespace App\Support;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

final class StockHistory
{
    /**
     * Mutasi stok sebuah item, terbaru di atas, untuk halaman detail.
     *
     * @return list<array<string, mixed>>
     */
    public static function forItem(Model $stockItem, int $limit = 50): array
    {
        return StockMovement::query()
            ->with('user:id,name')
            ->where('item_type', $stockItem->getMorphClass())
            ->where('item_id', $stockItem->id)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'type' => $movement->type,
                'status' => $movement->status,
                'quantity' => (int) $movement->quantity,
                'stock_before' => (int) $movement->stock_before,
                'stock_after' => (int) $movement->stock_after,
                'reference' => $movement->reference,
                'notes' => $movement->notes,
                'user_name' => $movement->user?->name,
                'movement_date' => $movement->movement_date?->toDateString(),
                'created_at' => $movement->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
