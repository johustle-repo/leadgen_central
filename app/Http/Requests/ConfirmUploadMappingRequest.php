<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmUploadMappingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('uploadBatch')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['mapping' => ['required', 'array'], 'mapping.*' => ['nullable', 'string', 'in:lead_date,company_name,website,address,city,state_province,country,country_code,industry,business_type,contact_person,position,email,phone,linkedin_url,import_trades,data_source,source_url,notes']];
    }
}
