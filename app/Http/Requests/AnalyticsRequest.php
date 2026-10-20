<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends DashboardRequest
{
    /** Demographics slicers that narrow the exported region, country, city and agent breakdowns. */
    public const DEMOGRAPHIC_FILTERS = ['region', 'country', 'city', 'agent'];

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
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:255'],
            'agent' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * The report period filters, without the demographics slicers.
     *
     * @return array<string, mixed>
     */
    public function reportFilters(): array
    {
        return $this->safe()->except(self::DEMOGRAPHIC_FILTERS);
    }

    /**
     * The demographics slicers that were set.
     *
     * @return array<string, string>
     */
    public function demographicFilters(): array
    {
        return array_filter($this->safe()->only(self::DEMOGRAPHIC_FILTERS), fn (mixed $value): bool => is_string($value) && $value !== '');
    }
}
