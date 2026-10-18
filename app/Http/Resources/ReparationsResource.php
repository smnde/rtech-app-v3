<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReparationsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice' => $this->invoice,
            'print_url' => route('servis.show', [$this->resource, 'print' => 1]),
            'item_name' => $this->item_name,
            'flag' => $this->flag ?? [],
            'accessories' => $this->accessories ?? [],
            'complaint' => $this->complaint ?? [],
            'condition' => $this->condition ?? [],
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'sequence' => $stage->sequence,
                'status' => $stage->status,
                'confirmed_at' => $stage->confirmed_at?->format('Y-m-d H:i'),
                'items' => $stage->items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'cost' => (float) $item->cost,
                    'est_days' => $item->est_days,
                ]),
            ])),
            'check_deadline_at' => $this->check_deadline_at?->format('Y-m-d\TH:i'),
            'process_deadline_at' => $this->process_deadline_at?->format('Y-m-d\TH:i'),
            'status' => $this->status,
            'payment_status' => $this->payment_status ?? 'unpaid',
            'paid_at' => $this->paid_at?->format('Y-m-d H:i'),
            'total_price' => $this->total_price === null ? null : (float) $this->total_price,
            'confirmed_total' => (float) $this->confirmedTotal(),
            'technician_notes' => $this->technician_notes,
            'additional_notes' => $this->additional_notes,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'customer_phone' => $this->customer?->phone,
            'created_at' => $this->created_at->toDateString(),
            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(fn ($log) => [
                'event' => $log->event,
                'from_value' => $log->from_value,
                'to_value' => $log->to_value,
                'note' => $log->note,
                'description' => $log->description,
                'user_name' => $log->user?->name,
                'occurred_at' => $log->occurred_at?->format('Y-m-d H:i'),
            ])),
        ];
    }
}
