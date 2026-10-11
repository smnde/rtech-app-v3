<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GadgetsResource extends JsonResource
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
            'serialnumber' => $this->serialnumber,
            'warranty' => $this->warranty,
            'stock' => $this->stock,
            'buy_price' => $this->buy_price,
            'sell_price' => $this->sell_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'specification' => $this->specification,
            'accessories' => $this->accessories ?? [],
            'issue_notes' => $this->issue_notes ?? [],
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand?->name,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
        ];
    }
}
