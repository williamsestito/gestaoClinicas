<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\LegalEntityType;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreatePatientResponsibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageResponsibles', $this->route('patient')) === true;
    }

    protected function prepareForValidation(): void
    {
        $merge = ['name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name'))];

        if ($this->filled('document')) {
            $merge['document'] = Document::onlyDigits((string) $this->input('document'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'document' => ['nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual)],
            'phone' => ['required', 'string', 'max:20'],
            'relationship' => ['required', 'string', 'max:255'],
            'is_legal_guardian' => ['sometimes', 'boolean'],
            'is_financial_responsible' => ['sometimes', 'boolean'],
            'is_authorized_representative' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roles = [
                $this->boolean('is_legal_guardian'),
                $this->boolean('is_financial_responsible'),
                $this->boolean('is_authorized_representative'),
            ];

            if (! in_array(true, $roles, true)) {
                $validator->errors()->add(
                    'is_legal_guardian',
                    'Selecione ao menos um papel: responsável legal, financeiro ou representante autorizado.',
                );
            }
        });
    }
}
