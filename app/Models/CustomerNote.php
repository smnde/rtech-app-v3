<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'type', 'status', 'description'])]

class CustomerNote extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
