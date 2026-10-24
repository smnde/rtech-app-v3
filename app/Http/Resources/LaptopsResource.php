<?php

namespace App\Http\Resources;

use App\Support\ItemImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaptopsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'code' => $this->code,
            'image' => ItemImage::url($this->image),
            'serialnumber' => $this->serialnumber,
            'warranty' => $this->warranty,
            'stock' => $this->stock,
            'buy_price' => $this->buy_price,
            'sell_price' => $this->sell_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'accessories' => $this->accessories ?? [],
            'features' => $this->features ?? [],
            'specification' => $this->specification,
            'issue_notes' => $this->issue_notes ?? [],
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand?->name,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
        ];
    }
}
