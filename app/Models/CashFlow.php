<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'item_type',
    'item_id',
    'type',
    'category',
    'description',
    'amount',
    'flow_date',
    'reference',
])]
class CashFlow extends Model
{
    public const CATEGORY_SALE = 'PENJUALAN';

    public const CATEGORY_PURCHASE = 'PEMBELIAN';

    public const CATEGORY_EXPENSE = 'BIAYA';

    public const CATEGORY_MANUAL = 'MANUAL';
    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:2',
            'flow_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->morphTo();
    }
}
