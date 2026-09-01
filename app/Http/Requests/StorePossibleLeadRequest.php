<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePossibleLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }

        if ($this->filled('contact_person')) {
            $this->merge(['contact_person' => Str::title(Str::lower(Str::squish((string) $this->input('contact_person'))))]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canViewAllLeads() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...StoreLeadRequest::leadRules(),
            'agent_id' => [
                'required',
                'integer',
                Rule::exists((new User)->getTable(), 'id')->where('role', UserRole::Agent->value)->where('status', 'active'),
            ],
            'lead_date' => ['required', 'date'],
        ];
    }
}
