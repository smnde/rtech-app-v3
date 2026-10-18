<?php

namespace App\Http\Requests\Reparation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeReparationStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(['RECEIVED', 'CHECKED', 'CONFIRMED', 'WAITING CONFIRMATION', 'ON PROCESS', 'PENDING', 'CANCEL', 'DONE', 'TAKEN'])],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid'])],
        ];
    }
}
