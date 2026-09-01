<?php

namespace App\Http\Requests;

use App\LeadStatus;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreLeadAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead
            && $lead->status === LeadStatus::PossibleLead
            && $this->user()?->canViewAllLeads() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachment' => ['required', File::types(['pdf', 'csv', 'xls', 'xlsx', 'doc', 'docx'])->max('20mb')],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}
