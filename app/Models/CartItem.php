<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'cart_id',
    'item_type',
    'item_id',
    'qty',
    'price',
    'discount',
    'is_bonus',
])]
class CartItem extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'is_bonus' => 'boolean',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): MorphTo
    {
        return $this->morphTo('product', 'item_type', 'item_id');
    }
}
