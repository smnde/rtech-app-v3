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

    public function changeStatus(Reparation $reparation, User $user, string $to, ?string $note = null): void
    {
        $from = $reparation->status;
        if ($from === $to) {
            return;
        }

        DB::transaction(function () use ($reparation, $user, $from, $to, $note) {
            $reparation->update(['status' => $to]);
            $this->record($reparation, $user, ReparationLog::EVENT_STATUS_CHANGED, $from, $to, $note);
        });
    }

    private function describe(string $event, ?string $from, ?string $to, ?string $note): string
    {
        return match ($event) {
            ReparationLog::EVENT_RECEIVED => 'Servis diterima dengan status RECEIVED.',
            ReparationLog::EVENT_STATUS_CHANGED => "Status berubah dari {$from} ke {$to}.",
            ReparationLog::EVENT_DEADLINE_CHANGED => "Deadline diubah: {$to}.",
            ReparationLog::EVENT_ESTIMATE_CHANGED => "Estimasi biaya diubah dari {$from} ke {$to}.",
            ReparationLog::EVENT_MESSAGE_SENT => 'Pesan dikirim ke customer.'.($note ? " {$note}" : ''),
            default => $note ?? $event,
        };
    }
}
