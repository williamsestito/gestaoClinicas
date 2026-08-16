<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use App\Enums\LegalEntityType;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\BrazilianState;
use App\Support\Documents\Document;
use App\Support\Patients\PatientAddressBlockGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edição, pelo próprio paciente/responsável, dos dados de um Patient já
 * vinculado à conta (App\Models\PatientUser) — nunca campos só-de-staff
 * como preferred_unit_id/primary_professional_id/status (ver
 * App\Http\Requests\Organization\UpdatePatientRequest para o equivalente
 * administrativo, que expõe esses campos).
 */
class UpdatePatientPortalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

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
        $patientId = $this->route('patient');

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual),
                Rule::unique('patients', 'document')->ignore(is_string($patientId) ? $patientId : null),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

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
        });
    }

    public function messages(): array
    {
        return [
            // Mensagem deliberadamente genérica (diferente do equivalente
            // administrativo, UpdatePatientRequest) — confirmar que um CPF
            // específico já pertence a OUTRO cadastro da clínica exporia
            // dado de terceiros que nunca se cadastraram no portal (achado
            // de security-review desta etapa).
            'document.unique' => 'Não foi possível salvar este documento. Confirme os dados ou entre em contato com a clínica.',
        ];
    }
}
