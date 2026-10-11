<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'phone', 'address', 'is_member'])]

class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'is_member' => 'boolean',
        ];
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function histories()
    {
        return $this->hasMany(CustomerHistory::class);
    }

    public function notes()
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function member()
    {
        return $this->hasOne(Membership::class);
    }
}
