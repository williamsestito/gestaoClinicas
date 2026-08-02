<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\LegalEntityType;
use App\Models\Professional;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfessionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('professional')) === true;
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
        /** @var Professional|null $professional */
        $professional = $this->route('professional');
        $organizationId = $professional?->organization_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'social_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => [
                'nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual),
                Rule::unique('professionals', 'document')->where('organization_id', $organizationId)->ignore($professional?->id),
            ],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.unique' => 'Já existe um profissional com este documento nesta clínica.',
        ];
    }

    /**
     * O documento só entra no array quando efetivamente reenviado — deixar
     * o campo em branco na edição preserva o documento já cadastrado, em
     * vez de apagá-lo (o valor mostrado ao usuário já chega mascarado, então
     * reenviar o que está na tela sempre falharia a validação de CPF).
     *
     * @return array<string, mixed>
     */
    public function attributesForAction(): array
    {
        $attributes = [
            'name' => (string) $this->input('name'),
            'social_name' => $this->input('social_name'),
            'display_name' => (string) $this->input('display_name'),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'birth_date' => $this->input('birth_date'),
            'bio' => $this->input('bio'),
        ];

        if ($this->filled('document')) {
            $attributes['document'] = $this->input('document');
        }

        return $attributes;
    }
}
