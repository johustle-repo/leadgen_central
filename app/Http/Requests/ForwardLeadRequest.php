<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForwardLeadRequest extends FormRequest
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
        return ['forwarded_to' => ['nullable', 'integer', 'exists:users,id'], 'recipient_name' => ['nullable', 'string', 'max:255'], 'recipient_email' => ['nullable', 'email', 'max:255'], 'team' => ['nullable', 'string', 'max:120'], 'remarks' => ['nullable', 'string', 'max:2000']];
    }
}
