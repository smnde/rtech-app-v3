<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reparation_id', 'user_id', 'type', 'event', 'from_value', 'to_value', 'note', 'description', 'occurred_at'])]
class ReparationLog extends Model
{
    public const EVENT_RECEIVED = 'RECEIVED';

    public const EVENT_STATUS_CHANGED = 'STATUS_CHANGED';

    public const EVENT_DEADLINE_CHANGED = 'DEADLINE_CHANGED';

    public const EVENT_ESTIMATE_CHANGED = 'ESTIMATE_CHANGED';

    public const EVENT_MESSAGE_SENT = 'MESSAGE_SENT';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function reparation()
    {
        return $this->belongsTo(Reparation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
