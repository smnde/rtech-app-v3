<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'type',
    'value',
    'starts_at',
    'ends_at',
    'is_active',
    'item_types',
])]
class Promo extends Model
{
    use HasFactory;

    public const TYPE_NOMINAL = 'nominal';

    public const TYPE_PERCENT = 'percent';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'item_types' => 'array',
        ];
    }

    /**
     * Promo yang sedang berlaku hari ini.
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where('starts_at', '<=', $today)
            ->where('ends_at', '>=', $today);
    }

    /**
     * Potongan untuk satu unit barang dengan harga tertentu.
     */
    public function discountForUnit(float $unitPrice): float
    {
        return $this->type === self::TYPE_PERCENT
            ? round($unitPrice * (float) $this->value / 100, 2)
            : (float) $this->value;
    }
}
