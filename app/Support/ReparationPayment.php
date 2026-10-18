<?php

namespace App\Support;

use App\Models\Reparation;
use Illuminate\Contracts\Auth\Authenticatable;

final class ReparationPayment
{
    /**
     * Simpan total servis terbaru dan tandai lunas bila diminta.
     *
     * Mutasi kas hanya dibuat sekali per invoice, jadi memanggil ini berulang
     * (DONE lalu TAKEN, atau klik dobel) tidak menggandakan saldo kas.
     *
     * @return bool true kalau mutasi kas baru tercatat.
     */
    public static function settle(Reparation $reparation, bool $paid, ?Authenticatable $user): bool
    {
        $total = $reparation->confirmedTotal();

        if ((float) $reparation->total_price !== $total) {
            $reparation->update(['total_price' => $total]);
        }

        if (! $paid) {
            return false;
        }

        if (! $reparation->isPaid()) {
            $reparation->update(['payment_status' => 'paid', 'paid_at' => now()]);
        }

        return CashFlowRecorder::recordServiceIncome($reparation, $user);
    }

    /**
     * Segarkan snapshot total tanpa menyentuh status pembayaran.
     */
    public static function syncTotal(Reparation $reparation): void
    {
        $total = $reparation->confirmedTotal();

        if ((float) $reparation->total_price !== $total) {
            $reparation->update(['total_price' => $total]);
        }
    }
}
