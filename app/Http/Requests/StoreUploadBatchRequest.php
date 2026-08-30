<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use App\Models\UploadBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUploadBatchRequest extends FormRequest
{
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
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(SystemSetting::where('key', 'csv_max_kilobytes')->value('value') ?? config('leadgen.csv_max_kilobytes', 5120))]];
    }
}
