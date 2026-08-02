<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Support\Documents\BrazilianState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateProfessionalRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Professional|null $professional */
        $professional = $this->route('professional');

        return $this->user()?->can('create', [ProfessionalRegistration::class, $professional?->organization]) === true;
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
            'registration_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9.\-\/]+$/'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(BrazilianState::codes())],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Professional|null $professional */
            $professional = $this->route('professional');

            if (! $professional || ! $this->filled('council') || ! $this->filled('registration_number')) {
                return;
            }

            $exists = ProfessionalRegistration::query()
                ->where('organization_id', $professional->organization_id)
                ->where('council', $this->input('council'))
                ->where('registration_number', $this->input('registration_number'))
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

    /** @return array{council: string, registration_type: ?string, registration_number: string, state: ?string, issued_at: ?string, expires_at: ?string, internal_notes: ?string} */
    public function attributesForAction(): array
    {
        return [
            'council' => (string) $this->input('council'),
            'registration_type' => $this->input('registration_type'),
            'registration_number' => (string) $this->input('registration_number'),
            'state' => $this->input('state'),
            'issued_at' => $this->input('issued_at'),
            'expires_at' => $this->input('expires_at'),
            'internal_notes' => $this->input('internal_notes'),
        ];
    }
}
