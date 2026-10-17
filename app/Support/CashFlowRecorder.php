<?php

namespace App\Support;

use App\Models\CashFlow;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class CashFlowRecorder
{
    public const SALE_CATEGORY = 'PENJUALAN';

    public const PURCHASE_CATEGORY = 'PEMBELIAN';

    public static function record(
        string $type,
        string $category,
        string $description,
        float $amount,
        ?Model $transaction,
        ?Authenticatable $user,
        ?string $reference = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        CashFlow::create([
            'user_id' => $user?->id,
            'item_type' => $transaction?->getMorphClass(),
            'item_id' => $transaction?->id,
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'flow_date' => now()->toDateString(),
            'reference' => $reference,
        ]);
    }

    /**
     * Hapus seluruh mutasi kas yang terkait dengan invoice tertentu.
     */
    public static function deleteByReference(string $reference): void
    {
        CashFlow::query()->where('reference', $reference)->delete();
    }
}
