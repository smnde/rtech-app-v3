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
            'estimated_cost' => ['required', 'string', 'max:100'],
            'process_deadline_at' => ['required', 'date'],
        ];
    }
}
