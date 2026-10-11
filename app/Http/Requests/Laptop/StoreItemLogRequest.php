<?php

namespace App\Http\Requests\Laptop;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemLogRequest extends FormRequest
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
            'type' => ['required', Rule::in(['upgrade', 'repair', 'note'])],
            'description' => ['required', 'string', 'max:1000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status_after' => ['nullable', 'required_if:type,repair', Rule::in(['ON PROSES', 'SIAP JUAL', 'PENDING', 'KENDALA', 'DISPLAY'])],
            'cpu' => ['nullable', 'string', 'max:255'],
            'gpu' => ['nullable', 'string', 'max:255'],
            'memory' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'display' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status_after.required_if' => 'Status setelah perbaikan wajib dipilih.',
        ];
    }
}
