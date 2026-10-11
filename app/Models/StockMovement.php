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
    'quantity',
    'stock_before',
    'stock_after',
    'reference',
    'notes',
])]
class StockMovement extends Model
{
    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
