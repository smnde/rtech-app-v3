<?php

namespace App\Models;

use App\Models\Concerns\FiltersStock;
use App\Models\Concerns\GeneratesSku;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category_id',
    'brand_id',
    'supplier_id',
    'name',
    'sku',
    'warranty',
    'stock',
    'buy_price',
    'sell_price',
    'notes',
    'status',
])]

class Sparepart extends Model
{
    use FiltersStock, GeneratesSku;

    protected function filterableColumns(): array
    {
        return ['brand_id', 'supplier_id', 'category_id'];
    }

    public function skuPrefix(): string
    {
        return 'SPT';
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
