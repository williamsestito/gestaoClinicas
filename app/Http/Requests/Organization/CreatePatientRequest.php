<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\LegalEntityType;
use App\Models\Patient;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\BrazilianState;
use App\Support\Documents\Document;
use App\Support\Patients\MinorGuardianGuard;
use App\Support\Patients\PatientAddressBlockGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [Patient::class, $organization]) === true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name')),
            'preferred_name' => CreateSpecialtyRequest::normalizeSpaces($this->input('preferred_name')),
        ];

        if ($this->filled('document')) {
            $merge['document'] = Document::onlyDigits((string) $this->input('document'));
        }

        foreach ((array) $this->input('responsibles', []) as $index => $responsible) {
            if (isset($responsible['document']) && $responsible['document'] !== '') {
                $merge["responsibles.{$index}.document"] = Document::onlyDigits((string) $responsible['document']);
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual),
                // whereNull('deleted_at'): espelha o índice único parcial do
                // banco (patients_unique_active_document, ver migration) —
                // sem isso, um CPF de um paciente arquivado nunca poderia
                // ser reutilizado por um cadastro novo.
                Rule::unique('patients', 'document')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at'),
            ],
            'birth_date' => ['required', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'origin' => ['nullable', 'string', 'max:255'],
            'preferred_unit_id' => ['nullable', 'string', Rule::exists('units', 'id')->where('organization_id', $organizationId)],
            'primary_professional_id' => ['nullable', 'string', Rule::exists('professionals', 'id')->where('organization_id', $organizationId)],

            'address' => ['nullable', 'array'],
            'address.postal_code' => ['nullable', 'string'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'size:2', Rule::in(BrazilianState::codes())],

            'emergency_contacts' => ['required', 'array', 'min:1'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.phone_primary' => ['required', 'string', 'max:20'],
            'emergency_contacts.*.phone_secondary' => ['nullable', 'string', 'max:20'],

            'responsibles' => ['sometimes', 'array'],
            'responsibles.*.name' => ['required_with:responsibles', 'string', 'max:255'],
            'responsibles.*.document' => ['nullable', 'string'],
            'responsibles.*.phone' => ['required_with:responsibles', 'string', 'max:20'],
            'responsibles.*.relationship' => ['required_with:responsibles', 'string', 'max:255'],
            'responsibles.*.is_legal_guardian' => ['sometimes', 'boolean'],
            'responsibles.*.is_financial_responsible' => ['sometimes', 'boolean'],
            'responsibles.*.is_authorized_representative' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            PatientAddressBlockGuard::assertCompleteOrEmpty((array) $this->input('address', []), $validator);
            $this->assertMinorHasLegalGuardian($validator);
        });
    }

    private function assertMinorHasLegalGuardian(Validator $validator): void
    {
        $birthDate = $this->input('birth_date');

        if (! is_string($birthDate) || $birthDate === '' || ! MinorGuardianGuard::isMinor($birthDate)) {
            return;
        }

        $responsibles = (array) $this->input('responsibles', []);

        if (! MinorGuardianGuard::hasLegalGuardianInPayload($responsibles)) {
            $validator->errors()->add('responsibles', 'Paciente menor de 18 anos precisa de ao menos um responsável legal informado.');
        }
    }

    public function messages(): array
    {
        return [
            'document.unique' => 'Já existe um paciente com este documento nesta clínica.',
        ];
    }
}
