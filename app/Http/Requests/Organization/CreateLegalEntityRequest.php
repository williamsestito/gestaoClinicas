<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\LegalEntityType;
use App\Models\LegalEntity;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\BrazilianState;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [LegalEntity::class, $organization]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LegalEntityType::class)],
            'document' => ['required', 'string', 'unique:legal_entities,document', new CpfCnpjRule($this->legalEntityType())],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'state_registration' => ['nullable', 'string', 'max:255'],
            'municipal_registration' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],

            'address.postal_code' => ['required', 'string'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.state' => ['required', 'string', 'size:2', Rule::in(BrazilianState::codes())],
        ];
    }

    private function legalEntityType(): LegalEntityType
    {
        return LegalEntityType::tryFrom((string) $this->input('type')) ?? LegalEntityType::Individual;
    }
}
