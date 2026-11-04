<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollMonthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-payroll') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'usd_to_php_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'internet_php' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'electricity_php' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'water_php' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
