<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\MedicalRecordFileCategory;
use App\Rules\ValidImageContentRule;
use App\Rules\ValidPdfContentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalRecordFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFiles', $this->route('medicalRecord')) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $extension = strtolower((string) $this->file('file')?->getClientOriginalExtension());
        $contentRule = $extension === 'pdf' ? new ValidPdfContentRule : new ValidImageContentRule;

        return [
            'category' => ['required', Rule::enum(MedicalRecordFileCategory::class)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240', $contentRule],
        ];
    }
}
