<?php

namespace App\Support;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class StockMovementRecorder
{
    public static function record(Model $stockItem, string $type, int $quantity, int $stockBefore, string $status, ?User $user, string $reference, ?string $at = null): void
    {
        $recordedAt = $at ? Carbon::parse($at) : now();

        StockMovement::create([
            'item_type' => $stockItem->getMorphClass(),
            'item_id' => $stockItem->id,
            'user_id' => $user?->id,
            'type' => $type,
            'status' => $status,
            'movement_date' => $recordedAt->toDateString(),
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $type === 'in' ? $stockBefore + $quantity : $stockBefore - $quantity,
            'reference' => $reference,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ]);
    }
}
