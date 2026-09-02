<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('release', $this->route('medicalRecord')) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
