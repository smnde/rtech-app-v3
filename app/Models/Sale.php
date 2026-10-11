<?php

namespace App\Models;

use App\Models\Concerns\HasInvoiceNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'customer_id',
    'user_id',
    'invoice',
    'subtotal',
    'discount',
    'total',
    'payment_status',
    'dp_amount',
    'paid_at',
    'notes',
])]
class Sale extends Model
{
    use HasInvoiceNumber;

    protected function invoicePrefix(): string
    {
        return 'INV';
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
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

    public function details()
    {
        return $this->hasMany(SalesDetail::class, 'sales_id');
    }
}
