<?php

namespace App\Http\Requests\Laptop;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaptopRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:255'],
            'serialnumber' => ['nullable', 'string', 'max:255', Rule::unique('laptops', 'serialnumber')->ignore($this->route('laptop'))],
            'warranty' => ['required', 'string', 'max:255'],
            'stock' => [$this->route('laptop') ? 'prohibited' : 'required', 'integer', 'min:0'],
            'buy_price' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['ON PROSES', 'SIAP JUAL', 'DISPLAY', 'PENDING', 'KENDALA', 'HABIS'])],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:255'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'specification' => ['nullable', 'array'],
            'specification.cpu' => ['nullable', 'string', 'max:255'],
            'specification.gpu' => ['nullable', 'string', 'max:255'],
            'specification.memory' => ['nullable', 'string', 'max:255'],
            'specification.storage' => ['nullable', 'string', 'max:255'],
            'specification.display' => ['nullable', 'string', 'max:255'],
            'issue_notes' => ['nullable', 'array'],
            'issue_notes.*' => ['string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
