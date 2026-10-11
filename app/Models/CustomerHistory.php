<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'reference_id', 'types', 'notes'])]

class CustomerHistory extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
