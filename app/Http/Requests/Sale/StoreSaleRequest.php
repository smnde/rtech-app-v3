<?php

namespace App\Http\Requests\Sale;

use App\Support\StockItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_status' => ['required', Rule::in(['lunas', 'hutang', 'keep'])],
            'payment_method' => ['required', Rule::in(['cash', 'tf'])],
            'dp_amount' => ['nullable', 'numeric', 'min:0', 'required_if:payment_status,keep'],
            'paid_at' => ['nullable', 'date', 'required_if:payment_status,keep'],
            'notes' => ['nullable', 'string', 'max:500'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', Rule::in(array_keys(StockItem::types()))],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_bonus' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dp_amount.required_if' => 'DP wajib diisi untuk status keep.',
            'paid_at.required_if' => 'Tanggal pelunasan wajib diisi untuk status keep.',
        ];
    }
}
