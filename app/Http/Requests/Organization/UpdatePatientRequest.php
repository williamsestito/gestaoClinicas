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

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('patient')) === true;
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

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;
        $patient = $this->route('patient');

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual),
                // whereNull('deleted_at'): espelha o índice único parcial do
                // banco (patients_unique_active_document, ver migration) —
                // sem isso, um paciente arquivado com o mesmo CPF bloqueava
                // até o próprio dono do CPF de salvar o cadastro ativo dele.
                Rule::unique('patients', 'document')
                    ->where('organization_id', $organizationId)
                    ->ignore($patient)
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            PatientAddressBlockGuard::assertCompleteOrEmpty((array) $this->input('address', []), $validator);
            $this->assertMinorHasLegalGuardian($validator);
        });
    }

    /**
     * Ao contrário da criação (onde os responsáveis vêm no mesmo payload),
     * aqui a checagem é contra os responsáveis já persistidos — trocar a
     * data de nascimento para menor sem um responsável legal ativo já
     * cadastrado é bloqueado, pedindo para resolver pela aba de
     * responsáveis primeiro.
     */
    private function assertMinorHasLegalGuardian(Validator $validator): void
    {
        $birthDate = $this->input('birth_date');

        if (! is_string($birthDate) || $birthDate === '' || ! MinorGuardianGuard::isMinor($birthDate)) {
            return;
        }

        /** @var Patient $patient */
        $patient = $this->route('patient');

        $hasActiveLegalGuardian = $patient->responsibles()
            ->where('is_legal_guardian', true)
            ->exists();

        if (! $hasActiveLegalGuardian) {
            $validator->errors()->add(
                'birth_date',
                'Não é possível marcar o paciente como menor de 18 anos sem um responsável legal já cadastrado. Adicione um responsável antes.',
            );
        }
    }

    public function messages(): array
    {
        return [
            'document.unique' => 'Já existe um paciente com este documento nesta clínica.',
        ];
    }
}
