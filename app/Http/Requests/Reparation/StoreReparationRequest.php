<?php

namespace App\Http\Requests\Reparation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReparationRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'flag' => ['required', 'array', 'min:1'],
            'flag.*' => ['string', Rule::in(['HW', 'SW', 'MB'])],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:255'],
            'complaint' => ['required', 'array', 'min:1'],
            'complaint.*' => ['string', 'max:255'],
            'condition' => ['nullable', 'array'],
            'condition.*' => ['string', 'max:255'],
            'check_deadline_at' => ['nullable', 'date'],
            'process_deadline_at' => ['nullable', 'date', 'after_or_equal:check_deadline_at'],
            'technician_notes' => ['nullable', 'string'],
            'additional_notes' => ['nullable', 'string'],
        ];
    }
}
