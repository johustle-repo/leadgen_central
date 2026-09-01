<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use App\Models\UploadBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUploadBatchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'duplicate_handling' => $this->input('duplicate_handling', 'flag'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', UploadBatch::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maximumKilobytes = SystemSetting::where('key', 'csv_max_kilobytes')->value('value') ?? config('leadgen.csv_max_kilobytes', 5120);
        $maximumFiles = SystemSetting::where('key', 'csv_max_files')->value('value') ?? config('leadgen.csv_max_files', 50);
        $fileRules = ['file', 'mimes:csv,txt', 'max:'.$maximumKilobytes];

        return [
            'file' => ['required_without:files', ...$fileRules],
            'files' => ['required_without:file', 'array', 'min:1', 'max:'.$maximumFiles],
            'files.*' => ['required', ...$fileRules],
            'duplicate_handling' => ['required', Rule::in(['flag', 'update_missing'])],
        ];
    }
}
