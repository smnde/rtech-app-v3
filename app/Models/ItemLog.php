<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'item_type',
    'item_id',
    'user_id',
    'assigned_to',
    'type',
    'description',
    'spec_before',
    'spec_after',
    'status_before',
    'status_after',
])]
class ItemLog extends Model
{
    protected function casts(): array
    {
        return [
            'spec_before' => 'array',
            'spec_after' => 'array',
        ];
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
