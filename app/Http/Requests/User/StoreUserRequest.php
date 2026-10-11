<?php

namespace App\Http\Requests\User;

use App\Support\Permissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('add user');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:10', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'role' => ['required', Rule::in(Permissions::roleNames()), $this->onlySysadminCanAssignSysadmin()],
            'password' => ['required', 'string', 'min:8', 'max:50', 'confirmed'],
        ];
    }

    private function onlySysadminCanAssignSysadmin(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->user()->canAssignRole($value)) {
                $fail('Hanya Sysadmin yang dapat memberi role Sysadmin.');
            }
        };
    }
}
