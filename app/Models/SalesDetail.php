<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sales_id', 'item_type', 'item_id', 'qty', 'price', 'subtotal'])]
class SalesDetail extends Model
{
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sales_id');
    }

    public function item()
    {
        return $this->morphTo();
    }
}
