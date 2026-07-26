<?php

namespace App\Concerns;

use App\Enums\LegalEntityType;
use App\Models\User;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\BrazilianState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'phone' => ['nullable', 'string', 'max:20'],
            'cpf' => [
                'nullable',
                'string',
                new CpfCnpjRule(LegalEntityType::Individual),
                $userId === null
                    ? Rule::unique(User::class, 'cpf')
                    : Rule::unique(User::class, 'cpf')->ignore($userId),
            ],
            'address_postal_code' => ['nullable', 'digits:8'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:100'],
            'address_neighborhood' => ['nullable', 'string', 'max:100'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_state' => ['nullable', 'string', Rule::in(BrazilianState::codes())],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
