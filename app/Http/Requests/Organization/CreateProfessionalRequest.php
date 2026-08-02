<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\LegalEntityType;
use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateProfessionalRequest extends FormRequest
{
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => [
                'nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual),
                Rule::unique('professionals', 'document')->where('organization_id', $organizationId),
            ],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('user_id');

            if ($userId === null || $userId === '') {
                return;
            }

            $organizationId = app(TenantContext::class)->organization()?->id;

            $hasActiveMembership = OrganizationMembership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('status', OrganizationMembershipStatus::Active)
                ->exists();

            if (! $hasActiveMembership) {
                $validator->errors()->add('user_id', 'O usuário selecionado não possui vínculo ativo com esta clínica.');

                return;
            }

            $alreadyLinked = Professional::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('user_id', 'Este usuário já está vinculado a outro profissional nesta clínica.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'document.unique' => 'Já existe um profissional com este documento nesta clínica.',
            'user_id.exists' => 'Selecione um usuário válido.',
        ];
    }

    /** @return array{name: string, social_name: ?string, display_name: string, email: ?string, phone: ?string, document: ?string, birth_date: ?string, bio: ?string, user_id: ?int} */
    public function attributesForAction(): array
    {
        $name = (string) $this->input('name');
        $displayName = $this->input('display_name');
        $userId = $this->input('user_id');

        return [
            'name' => $name,
            'social_name' => $this->input('social_name'),
            'display_name' => is_string($displayName) && $displayName !== '' ? $displayName : ($this->input('social_name') ?: $name),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'document' => $this->input('document'),
            'birth_date' => $this->input('birth_date'),
            'bio' => $this->input('bio'),
            'user_id' => $userId === null || $userId === '' ? null : (int) $userId,
        ];
    }
}
