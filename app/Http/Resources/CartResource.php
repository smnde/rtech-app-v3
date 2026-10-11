<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'label' => $this->label,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'total' => (float) $this->total,
            'item_count' => $this->item_count,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
                'name' => $item->product()?->name,
                'code' => $item->product()?->sku,
                'spec' => $this->formatSpec($item->product()?->specification),
                'qty' => $item->qty,
                'price' => (float) $item->price,
                'discount' => (float) $item->discount,
                'is_bonus' => $item->is_bonus,
            ])),
        ];
    }

    private function formatSpec(?array $specification): ?string
    {
        if (! $specification) {
            return null;
        }

        return collect($specification)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(' · ') ?: null;
    }
}
