<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use App\Concerns\PasswordValidationRules;
use App\Enums\LegalEntityType;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use App\Support\Patients\MinorGuardianGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Autocadastro público do portal do paciente. "registering_for" define se a
 * conta gerencia o próprio titular (menor não pode) ou um dependente (o
 * titular da conta não precisa ser ele mesmo paciente — caso do
 * responsável). Ver docs/modules/patient-portal.md.
 */
class RegisterPatientUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
        ];

        foreach (['document', 'dependent_document'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = Document::onlyDigits((string) $this->input($field));
            }
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
            'email' => ['required', 'email', 'max:255', Rule::unique('patient_users', 'email')],
            'password' => $this->passwordRules(),

            'registering_for' => ['required', 'in:self,dependent'],

            'birth_date' => ['required_if:registering_for,self', 'nullable', 'date', 'before:today'],
            'document' => ['nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual)],
            'phone' => ['nullable', 'string', 'max:20'],

            'dependent_name' => ['required_if:registering_for,dependent', 'nullable', 'string', 'min:2', 'max:255'],
            'dependent_birth_date' => ['required_if:registering_for,dependent', 'nullable', 'date', 'before:today'],
            'dependent_document' => ['nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual)],
            'dependent_phone' => ['nullable', 'string', 'max:20'],
            'relationship' => ['required_if:registering_for,dependent', 'nullable', 'string', 'max:255'],
            // Telefone de quem está se cadastrando como responsável — usado
            // para o contato de emergência (RN-003) e o registro de
            // responsável (RN-004) criados automaticamente, já que a conta
            // de portal em si não guarda telefone (ver
            // App\Actions\PatientPortal\AddDependentPatientAction).
            'responsible_phone' => ['required_if:registering_for,dependent', 'nullable', 'string', 'max:20'],

            // Honeypot: mesmo padrão de StoreAppointmentRequestRequest —
            // nunca preenchido por um visitante real (ver Controller).
            'website' => ['nullable', 'string'],
            'form_rendered_at' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('registering_for') !== 'self') {
                return;
            }

            $birthDate = $this->input('birth_date');

            if (is_string($birthDate) && $birthDate !== '' && MinorGuardianGuard::isMinor($birthDate)) {
                $validator->errors()->add('birth_date', 'Menores de 18 anos não podem se autocadastrar como titulares. Peça para um responsável se cadastre e adicione você como dependente.');
            }
        });
    }
}
