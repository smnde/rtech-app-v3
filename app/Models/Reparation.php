<?php

namespace App\Models;

use App\Models\Concerns\HasInvoiceNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'invoice',
    'customer_id',
    'user_id',
    'item_name',
    'flag',
    'accessories',
    'complaint',
    'condition',
    'estimated_cost',
    'check_deadline_at',
    'process_deadline_at',
    'status',
    'technician_notes',
    'additional_notes',
])]
class Reparation extends Model
{
    use HasInvoiceNumber;

    protected function invoicePrefix(): string
    {
        return 'SRV';
    }

    public const STATUSES = [
        'RECEIVED',
        'CHECKED',
        'CONFIRMED',
        'WAITING CONFIRMATION',
        'ON PROCESS',
        'PENDING',
        'CANCEL',
        'DONE',
        'TAKEN',
    ];

    protected function casts(): array
    {
        return [
            'flag' => 'array',
            'accessories' => 'array',
            'complaint' => 'array',
            'condition' => 'array',
            'check_deadline_at' => 'datetime',
            'process_deadline_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(ReparationLog::class);
    }
}
