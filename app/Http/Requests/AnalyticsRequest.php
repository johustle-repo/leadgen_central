<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends DashboardRequest
{
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
            ...parent::rules(),
            'period' => ['nullable', Rule::in(['today', 'week', 'last_week', 'month', 'last_month', '30_days', 'quarter', 'custom', '7_days', '90_days'])],
            'geo_country' => ['nullable', 'string', 'max:255'],
            'geo_province' => ['nullable', 'string', 'max:255'],
            'geo_city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
