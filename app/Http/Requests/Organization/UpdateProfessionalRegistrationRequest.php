<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\ProfessionalRegistration;
use App\Support\Documents\BrazilianState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfessionalRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('professionalRegistration')) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'council' => CreateSpecialtyRequest::normalizeSpaces($this->input('council')),
            'registration_type' => CreateSpecialtyRequest::normalizeSpaces($this->input('registration_type')),
            'registration_number' => CreateSpecialtyRequest::normalizeSpaces($this->input('registration_number')),
            'state' => $this->filled('state') ? mb_strtoupper(trim((string) $this->input('state'))) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'council' => ['required', 'string', 'max:100'],
            'registration_type' => ['nullable', 'string', 'max:100'],
            // Opcional na edição: o número exibido ao usuário já chega
            // mascarado, então em branco significa "manter o número atual"
            // (ver attributesForAction()) — nunca reenviar o valor mascarado.
            'registration_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9.\-\/]+$/'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(BrazilianState::codes())],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ProfessionalRegistration|null $registration */
            $registration = $this->route('professionalRegistration');

            if (! $registration || ! $this->filled('council')) {
                return;
            }

            $registrationNumber = $this->filled('registration_number')
                ? $this->input('registration_number')
                : $registration->registration_number;

            $exists = ProfessionalRegistration::query()
                ->where('id', '!=', $registration->id)
                ->where('organization_id', $registration->organization_id)
                ->where('council', $this->input('council'))
                ->where('registration_number', $registrationNumber)
                ->where('state', $this->input('state'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('registration_number', 'Já existe um vínculo ativo com este registro.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'registration_number.regex' => 'O número deve conter apenas letras, números, ponto, hífen ou barra.',
            'expires_at.after_or_equal' => 'A validade não pode ser anterior à data de emissão.',
            'state.in' => 'Informe uma UF brasileira válida.',
        ];
    }

    /**
     * O número só entra no array quando efetivamente reenviado — deixar o
     * campo em branco na edição preserva o número já cadastrado.
     *
     * @return array<string, mixed>
     */
    public function attributesForAction(): array
    {
        $attributes = [
            'council' => (string) $this->input('council'),
            'registration_type' => $this->input('registration_type'),
            'state' => $this->input('state'),
            'issued_at' => $this->input('issued_at'),
            'expires_at' => $this->input('expires_at'),
            'internal_notes' => $this->input('internal_notes'),
        ];

        if ($this->filled('registration_number')) {
            $attributes['registration_number'] = (string) $this->input('registration_number');
        }

        return $attributes;
    }
}
