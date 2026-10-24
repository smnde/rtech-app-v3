<?php

namespace App\Http\Requests\Gadget;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GadgetRequest extends FormRequest
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
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'serialnumber' => ['nullable', 'string', 'max:255', Rule::unique('gadgets', 'serialnumber')->ignore($this->route('gadget'))],
            'warranty' => ['required', 'string', 'max:255'],
            'stock' => [$this->route('gadget') ? 'prohibited' : 'required', 'integer', 'min:0'],
            'buy_price' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'specification' => ['nullable', 'array'],
            'specification.cpu' => ['nullable', 'string', 'max:255'],
            'specification.gpu' => ['nullable', 'string', 'max:255'],
            'specification.memory' => ['nullable', 'string', 'max:255'],
            'specification.storage' => ['nullable', 'string', 'max:255'],
            'specification.display' => ['nullable', 'string', 'max:255'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:255'],
            'issue_notes' => ['nullable', 'array'],
            'issue_notes.*' => ['string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['READY STOCK', 'HABIS'])],
        ];
    }
}
