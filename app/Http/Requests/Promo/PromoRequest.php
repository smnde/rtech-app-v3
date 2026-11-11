<?php

namespace App\Http\Requests\Promo;

use App\Models\Promo;
use App\Support\StockItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:3', 'max:100'],
            'type' => ['required', Rule::in([Promo::TYPE_NOMINAL, Promo::TYPE_PERCENT])],
            'value' => [
                'required',
                'numeric',
                'min:0',
                Rule::when($this->input('type') === Promo::TYPE_PERCENT, ['max:100']),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'item_types' => ['required', 'array', 'min:1'],
            'item_types.*' => [Rule::in(array_keys(StockItem::types()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'value.max' => 'Persen promo tidak boleh lebih dari 100%.',
            'item_types.required' => 'Pilih minimal satu jenis barang.',
        ];
    }
}
