<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->input('period') === 'custom') {
                $from = CarbonImmutable::parse($this->input('date_from'));
                $to = CarbonImmutable::parse($this->input('date_to'));
                if ($to->gt($from->addYears(10))) {
                    $validator->errors()->add('date_to', 'Select a reporting range of ten years or less.');
                }
            }
        }];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['today', 'week', 'last_week', 'month', 'last_month', '30_days', 'quarter', 'custom'])],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ];
    }
}
