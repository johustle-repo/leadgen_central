<?php

namespace App\Http\Requests;

use App\EmailReplyClassification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('emailReply'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classification' => ['sometimes', 'required', Rule::enum(EmailReplyClassification::class)],
            'is_read' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
