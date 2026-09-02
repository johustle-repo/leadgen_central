<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteUploadBatchesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'select_all' => ['sometimes', 'boolean'],
            'upload_batch_ids' => [Rule::requiredIf(! $this->boolean('select_all')), 'array', 'min:1', 'max:100'],
            'upload_batch_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('upload_batches', 'id'),
            ],
        ];
    }
}
