<?php

namespace App\Http\Requests;

use App\AccountStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $allowedRoles = array_map(fn ($role) => $role->value, $this->user()?->assignableRoles() ?? []);
        if ($target instanceof User && ! in_array($target->role->value, $allowedRoles, true)) {
            $allowedRoles[] = $target->role->value;
        }

        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)], 'password' => ['nullable', 'confirmed', Password::defaults()], 'role' => ['required', Rule::in($allowedRoles)], 'team' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::enum(AccountStatus::class)], 'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')->ignore($target)], 'alias_name' => ['nullable', 'string', 'max:255'], 'alias_email' => ['nullable', 'email', 'max:255']];
    }
}
