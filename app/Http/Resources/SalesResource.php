<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesResource extends JsonResource
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
            'customer_name' => $this->customer?->name,
            'total' => (float) $this->total,
            'payment_status' => $this->payment_status,
            'dp_amount' => $this->dp_amount !== null ? (float) $this->dp_amount : null,
            'paid_at' => $this->paid_at?->toDateString(),
        ];
    }
}
