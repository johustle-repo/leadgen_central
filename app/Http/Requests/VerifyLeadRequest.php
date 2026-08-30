<?php

namespace App\Http\Requests;

use App\LeadStatus;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('lead') instanceof Lead && $this->user()?->canViewAllLeads() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in([LeadStatus::PossibleLead->value, LeadStatus::QualifiedLead->value, LeadStatus::NotLead->value, LeadStatus::NeedsReview->value, LeadStatus::Duplicate->value])], 'intent' => ['nullable', Rule::in(['save', 'save_next'])], 'remarks' => ['nullable', 'string', 'max:2000'], 'company_name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:120'], 'country' => ['nullable', 'string', 'max:120'], 'country_code' => ['nullable', 'string', 'size:2'], 'timezone' => ['nullable', 'timezone:all'], 'contact_person' => ['nullable', 'string', 'max:255'], 'position' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'industry' => ['nullable', 'string', 'max:120']];
    }
}
