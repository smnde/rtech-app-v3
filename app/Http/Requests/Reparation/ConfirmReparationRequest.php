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
            'stage_id' => ['required', 'integer', 'exists:reparation_stages,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.cost' => ['required', 'string', 'max:100'],
            'items.*.est_days' => ['nullable', 'date'],
            'process_deadline_at' => ['required', 'date'],
        ];
    }
}
