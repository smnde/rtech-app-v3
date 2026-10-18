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
    'check_deadline_at',
    'process_deadline_at',
    'status',
    'payment_status',
    'paid_at',
    'total_price',
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
            'total_price' => 'decimal:2',
            'paid_at' => 'datetime',
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

    public function stages()
    {
        return $this->hasMany(ReparationStage::class)->orderBy('sequence');
    }

    /**
     * Total biaya dari item di semua tahap yang sudah dikonfirmasi.
     * Hitung dari relasi yang sudah dimuat agar tidak query per baris.
     */
    public function confirmedTotal(): float
    {
        if ($this->relationLoaded('stages')) {
            return (float) $this->stages
                ->where('status', 'CONFIRMED')
                ->flatMap(fn ($stage) => $stage->relationLoaded('items') ? $stage->items : [])
                ->sum('cost');
        }

        return (float) ReparationStageItem::query()
            ->whereHas('stage', fn ($query) => $query
                ->where('reparation_id', $this->id)
                ->where('status', 'CONFIRMED'))
            ->sum('cost');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
