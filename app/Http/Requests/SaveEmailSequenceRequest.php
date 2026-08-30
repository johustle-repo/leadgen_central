<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveEmailSequenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array', 'size:3'],
            'steps.*.day' => ['required', 'integer', 'in:1,3,7'],
            'steps.*.subject' => ['required', 'string', 'max:255'],
            'steps.*.body' => ['required', 'string', 'max:10000'],
            'steps.*.attach_brochure' => ['required', 'boolean'],
        ];
    }
}
