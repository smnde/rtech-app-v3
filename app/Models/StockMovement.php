<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'item_type',
    'item_id',
    'user_id',
    'type',
    'status',
    'movement_date',
    'quantity',
    'stock_before',
    'stock_after',
    'reference',
    'notes',
])]
class StockMovement extends Model
{
    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
        ];
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
