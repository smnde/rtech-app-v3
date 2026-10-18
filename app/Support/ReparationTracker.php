<?php

namespace App\Support;

use App\Models\Reparation;
use App\Models\ReparationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReparationTracker
{
    public function record(Reparation $reparation, User $user, string $event, ?string $from = null, ?string $to = null, ?string $note = null): ReparationLog
    {
        return $reparation->logs()->create([
            'user_id' => $user->id,
            'type' => 'status',
            'event' => $event,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
            'description' => $this->describe($event, $from, $to, $note),
            'occurred_at' => now(),
        ]);
    }

    public function changeStatus(Reparation $reparation, User $user, string $to, ?string $note = null, ?bool $paid = null): void
    {
        $from = $reparation->status;
        if ($from === $to) {
            return;
        }

        DB::transaction(function () use ($reparation, $user, $from, $to, $note, $paid) {
            $reparation->update(['status' => $to]);
            $this->record($reparation, $user, ReparationLog::EVENT_STATUS_CHANGED, $from, $to, $note);
            $this->syncPayment($reparation, $user, $to, $paid);
        });
    }

    /**
     * DONE/TAKEN adalah titik penagihan: total disimpan, dan kas dicatat hanya
     * saat pembayaran ditandai lunas (cukup sekali per invoice).
     */
    private function syncPayment(Reparation $reparation, User $user, string $to, ?bool $paid): void
    {
        if (! in_array($to, ['DONE', 'TAKEN'], true)) {
            ReparationPayment::syncTotal($reparation);

            return;
        }

        ReparationPayment::settle($reparation, $paid ?? $reparation->isPaid(), $user);
    }

    private function describe(string $event, ?string $from, ?string $to, ?string $note): string
    {
        return match ($event) {
            ReparationLog::EVENT_RECEIVED => 'RECEIVED. Servis diterima.',
            ReparationLog::EVENT_STATUS_CHANGED => $this->describeStatusChange($to, $note),
            default => $note ?? $event,
        };
    }

    private function describeStatusChange(?string $to, ?string $note): string
    {
        return match ($to) {
            'RECEIVED' => 'RECEIVED. Servis diterima.',
            'WAITING CONFIRMATION' => 'WAITING CONFIRMATION. Menunggu konfirmasi dari customer.',
            'ON PROCESS' => 'ON PROCESS. Dalam tahap pengerjaan.',
            'PENDING' => $note ? "PENDING. {$note}" : 'PENDING. Servis ditangguhkan.',
            'DONE' => 'DONE. Selesai, sudah dikonfirmasi ke customer.',
            'CANCEL' => $note ? "CANCEL. Dibatalkan, {$note}" : 'CANCEL. Dibatalkan.',
            'CHECKED' => 'CHECKED. Servis dicek teknisi.',
            'CONFIRMED' => 'CONFIRMED. Servis dikonfirmasi.',
            'TAKEN' => 'TAKEN. Unit diambil customer.',
            default => "{$to}. Status berubah.",
        };
    }
}
