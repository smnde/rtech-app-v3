<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasesResource extends JsonResource
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
            'invoice' => $this->invoice,
            'date' => $this->created_at->toDateString(),
            'source_name' => $this->supplier?->name ?? $this->customer?->name,
            'total' => (float) $this->total,
            'payment_status' => $this->payment_status,
            'po_type' => $this->po_type,
            'paid_at' => $this->paid_at?->toDateString(),
        ];
    }
}
