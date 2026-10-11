<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'customer_id',
    'type',
    'status',
    'label',
    'total',
    'item_count',
])]
class Cart extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public static function activeFor(User $user, string $type): self
    {
        return self::query()->firstOrCreate([
            'user_id' => $user->id,
            'type' => $type,
            'status' => 'active',
        ]);
    }
}
