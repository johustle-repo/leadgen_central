<?php

namespace App\Http\Requests;

use App\AccountStatus;
use App\UserRole;
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
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))], 'password' => ['nullable', 'confirmed', Password::defaults()], 'role' => ['required', Rule::enum(UserRole::class)], 'team' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::enum(AccountStatus::class)]];
    }
}
