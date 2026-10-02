<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadOwnerRequest extends FormRequest
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
        return [
            'agent_id' => [
                'required',
                'integer',
                Rule::exists((new User)->getTable(), 'id')->where('role', UserRole::Agent->value)->where('status', 'active'),
            ],
        ];
    }
}
