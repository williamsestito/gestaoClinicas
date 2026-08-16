<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Concerns\PasswordValidationRules;
use App\Enums\LegalEntityType;
use App\Models\Professional;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProfessionalRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [Professional::class, $organization]) === true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name')),
            'social_name' => CreateSpecialtyRequest::normalizeSpaces($this->input('social_name')),
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

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'social_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            // Todo profissional novo ganha um usuário de acesso próprio (ver
            // App\Actions\Organization\CreateProfessionalAction) — e-mail e
            // CPF passam a ser obrigatórios na criação (registros já
            // existentes sem esses dados não são afetados retroativamente).
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => [
                'required', 'string', new CpfCnpjRule(LegalEntityType::Individual),
                Rule::unique('professionals', 'document')->where('organization_id', $organizationId),
            ],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'password' => $this->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe um usuário cadastrado com este e-mail.',
            'document.unique' => 'Já existe um profissional com este documento nesta clínica.',
        ];
    }

    /** @return array{name: string, social_name: ?string, display_name: string, email: string, phone: ?string, document: string, birth_date: ?string, bio: ?string, password: string} */
    public function attributesForAction(): array
    {
        $name = (string) $this->input('name');
        $displayName = $this->input('display_name');

        return [
            'name' => $name,
            'social_name' => $this->input('social_name'),
            'display_name' => is_string($displayName) && $displayName !== '' ? $displayName : ($this->input('social_name') ?: $name),
            'email' => (string) $this->input('email'),
            'phone' => $this->input('phone'),
            'document' => (string) $this->input('document'),
            'birth_date' => $this->input('birth_date'),
            'bio' => $this->input('bio'),
            'password' => (string) $this->input('password'),
        ];
    }
}
