<?php

namespace App\Support;

use App\Models\CashFlow;
use App\Models\Reparation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class CashFlowRecorder
{
    public const SALE_CATEGORY = 'PENJUALAN';

    public const PURCHASE_CATEGORY = 'PEMBELIAN';

    public const SERVICE_CATEGORY = 'SERVIS';

    public static function record(
        string $type,
        string $category,
        string $description,
        float $amount,
        ?Model $transaction,
        ?Authenticatable $user,
        ?string $reference = null,
        ?string $flowDate = null,
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
            'flow_date' => $flowDate ?? now()->toDateString(),
            'reference' => $reference,
        ]);
    }

    /**
     * Catat uang masuk servis, dirinci per biaya yang dikonfirmasi.
     * Biaya 0 dilewati. Hanya sekali per invoice.
     *
     * @return bool true kalau mutasi baru tercatat.
     */
    public static function recordServiceIncome(Reparation $reparation, ?Authenticatable $user): bool
    {
        if (self::existsForReference($reparation->invoice)) {
            return false;
        }

        $items = $reparation->confirmedItems();

        if ($items->isEmpty()) {
            return false;
        }

        foreach ($items as $item) {
            self::record(
                'in',
                self::SERVICE_CATEGORY,
                "Servis {$reparation->invoice} — {$item['label']}",
                $item['cost'],
                $reparation,
                $user,
                $reparation->invoice,
            );
        }

        return true;
    }

    public static function existsForReference(string $reference): bool
    {
        return CashFlow::query()
            ->where('reference', $reference)
            ->where('type', 'in')
            ->exists();
    }

    /**
     * Hapus seluruh mutasi kas yang terkait dengan invoice tertentu.
     */
    public static function deleteByReference(string $reference): void
    {
        CashFlow::query()->where('reference', $reference)->delete();
    }
}
