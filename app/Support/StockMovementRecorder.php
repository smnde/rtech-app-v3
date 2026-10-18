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

        self::syncStatus($stockItem);
    }

    /**
     * Status stok mengikuti jumlah: 0 → HABIS, naik dari 0 → READY STOCK/SIAP JUAL.
     * Status khusus (DISPLAY, ON PROSES, PENDING, KENDALA) tidak ditimpa.
     *
     * @var array<string, array{ready: string, exhausted: string}>
     */
    private const STATUS_BY_TYPE = [
        'laptop' => ['ready' => 'SIAP JUAL', 'exhausted' => 'HABIS'],
        'gadget' => ['ready' => 'READY STOCK', 'exhausted' => 'HABIS'],
        'accessory' => ['ready' => 'READY STOCK', 'exhausted' => 'HABIS'],
        'sparepart' => ['ready' => 'READY STOCK', 'exhausted' => 'HABIS'],
    ];

    public static function syncStatus(Model $stockItem): void
    {
        $map = self::STATUS_BY_TYPE[$stockItem->getMorphClass()] ?? null;
        if ($map === null) {
            return;
        }

        $stock = (int) $stockItem->stock;
        $current = $stockItem->status;

        if ($stock <= 0) {
            $status = $map['exhausted'];
        } elseif (in_array($current, [null, '', $map['exhausted']], true)) {
            $status = $map['ready'];
        } else {
            return;
        }

        if ($current !== $status) {
            // Simpan nilai mentah: stock baru saja diubah lewat query builder,
            // jadi model lokal ini masih memegang angka lama.
            $stockItem->status = $status;
            $stockItem->saveQuietly();
        }
    }
}
