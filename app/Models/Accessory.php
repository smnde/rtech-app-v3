<?php

namespace App\Models;

use App\Models\Concerns\FiltersStock;
use App\Models\Concerns\GeneratesSku;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['brand_id', 'supplier_id', 'name', 'sku', 'warranty', 'stock', 'buy_price', 'sell_price', 'notes', 'status'])]

class Accessory extends Model
{
    use FiltersStock, GeneratesSku;

    public function skuPrefix(): string
    {
        return 'ACC';
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
