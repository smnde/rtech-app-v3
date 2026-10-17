<?php

namespace App\Models;

use App\Models\Concerns\HasInvoiceNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'supplier_id',
    'customer_id',
    'user_id',
    'buy_from_reference',
    'invoice',
    'total',
    'payment_status',
    'status',
    'paid_at',
    'notes',
])]
class Purchase extends Model
{
    use HasInvoiceNumber;

    protected function invoicePrefix(): string
    {
        return 'PRC';
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseDetail::class, 'purchase_id');
    }
}
