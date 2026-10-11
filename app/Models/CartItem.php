<?php

namespace App\Models;

use App\Support\StockItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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

    public function product(): ?Model
    {
        return StockItem::modelFor($this->item_type)::query()->find($this->item_id);
    }
}
