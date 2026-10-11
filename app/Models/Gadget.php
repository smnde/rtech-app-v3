<?php

namespace App\Models;

use App\Models\Concerns\FiltersStock;
use App\Models\Concerns\GeneratesSku;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'brand_id',
    'supplier_id',
    'name',
    'sku',
    'serialnumber',
    'warranty',
    'stock',
    'buy_price',
    'sell_price',
    'specification',
    'accessories',
    'issue_notes',
    'notes',
    'status',
])]

class Gadget extends Model
{
    use FiltersStock, GeneratesSku;

    protected $casts = [
        'specification' => 'array',
        'accessories' => 'array',
        'issue_notes' => 'array',
    ];

    protected function searchableColumns(): array
    {
        return ['name', 'sku', 'serialnumber'];
    }

    public function skuPrefix(): string
    {
        return 'GDT';
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
