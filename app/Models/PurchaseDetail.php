<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['purchase_id', 'item_type', 'item_id', 'qty', 'buy_price', 'subtotal'])]
class PurchaseDetail extends Model
{
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function item()
    {
        return $this->morphTo();
    }
}
