<?php

namespace App\Http\Requests\Reparation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmReparationRequest extends FormRequest
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
            'stage_id' => ['nullable', 'integer', 'exists:reparation_stages,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.est_days' => ['nullable', 'date'],
            'process_deadline_at' => ['required', 'date'],
            'message' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
