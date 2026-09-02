<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('medicalRecord')) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'anamnesis' => ['nullable', 'string', 'max:10000'],
            'preexisting_conditions' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'current_medications' => ['nullable', 'string', 'max:5000'],
            'contraindications' => ['nullable', 'string', 'max:5000'],
            'evaluation' => ['nullable', 'string', 'max:10000'],
            'treatment_plan' => ['nullable', 'string', 'max:10000'],
            'procedures_performed' => ['nullable', 'string', 'max:10000'],
            'evolution_notes' => ['nullable', 'string', 'max:10000'],
            'prescriptions' => ['nullable', 'string', 'max:5000'],
            'referrals' => ['nullable', 'string', 'max:5000'],
            'specialty_data' => ['nullable', 'array'],
            'has_return_right' => ['required', 'boolean'],
            'return_window_days' => ['nullable', 'required_if:has_return_right,true', 'integer', 'min:1', 'max:365'],
        ];
    }
}
