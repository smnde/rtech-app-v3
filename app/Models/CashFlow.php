<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'type',
    'category',
    'description',
    'amount',
    'flow_date',
])]
class CashFlow extends Model
{
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
}
