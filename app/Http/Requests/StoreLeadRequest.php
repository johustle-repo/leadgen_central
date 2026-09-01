<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }

        if ($this->filled('contact_person')) {
            $this->merge([
                'contact_person' => Str::title(Str::lower(Str::squish((string) $this->input('contact_person')))),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::leadRules();
    }

    /** @return array<string, list<string>> */
    public static function leadRules(): array
    {
        return ['agent_id' => ['nullable', 'integer', 'exists:users,id'], 'lead_date' => ['nullable', 'date'], 'company_name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:120'], 'state_province' => ['nullable', 'string', 'max:120'], 'country' => ['nullable', 'string', 'max:120'], 'country_code' => ['nullable', 'string', 'size:2'], 'timezone' => ['nullable', 'string', 'max:80'], 'industry' => ['nullable', 'string', 'max:120'], 'business_type' => ['nullable', 'string', 'max:120'], 'contact_person' => ['nullable', 'string', 'max:255'], 'position' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'linkedin_url' => ['nullable', 'url:http,https', 'max:255'], 'import_trades' => ['nullable', 'string', 'max:255'], 'data_source' => ['nullable', 'string', Rule::in(['Tendata', 'Lusha', 'Tendata/Lusha', 'Email', 'Manual'])], 'source_url' => ['nullable', 'url:http,https', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
