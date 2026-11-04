<?php

namespace App\Http\Requests;

use App\EmploymentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollMemberRequest extends FormRequest
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
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'joined_on' => ['required', 'date'],
        ];
    }
}
