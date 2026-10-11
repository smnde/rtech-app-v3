<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'points'])]

class Membership extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
