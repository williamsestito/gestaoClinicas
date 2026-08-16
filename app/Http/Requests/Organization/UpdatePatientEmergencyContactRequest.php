<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageEmergencyContacts', $this->route('patient')) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'relationship' => ['required', 'string', 'max:255'],
            'phone_primary' => ['required', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
        ];
    }
}
