<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use App\Enums\LegalEntityType;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adiciona um novo dependente a uma conta de portal já autenticada — mesma
 * validação usada no "dependent" do autocadastro
 * (App\Http\Requests\PatientPortal\RegisterPatientUserRequest), reaproveitada
 * pela Action (App\Actions\PatientPortal\AddDependentPatientAction).
 */
class AddDependentPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('document')) {
            $this->merge(['document' => Document::onlyDigits((string) $this->input('document'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'document' => ['nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual)],
            'phone' => ['nullable', 'string', 'max:20'],
            'relationship' => ['required', 'string', 'max:255'],
            // Telefone do responsável (a própria conta) — usado para o
            // contato de emergência (RN-003) e o responsável (RN-004)
            // criados automaticamente, ver AddDependentPatientAction.
            'responsible_phone' => ['required', 'string', 'max:20'],
        ];
    }
}
