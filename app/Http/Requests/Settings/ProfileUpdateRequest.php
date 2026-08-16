<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\Documents\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Normaliza o CPF para dígitos antes da validação — precisa acontecer
     * aqui, e não depois (ex.: no controller), porque a regra de
     * unicidade (Rule::unique) e a comparação com o valor já salvo no
     * banco (sempre dígitos apenas) precisam comparar a mesma
     * representação. Validar a versão mascarada deixaria passar um CPF
     * duplicado, que só seria barrado pela constraint do banco com um
     * erro 500 em vez de um erro de validação.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('cpf')) {
            $this->merge(['cpf' => Document::onlyDigits((string) $this->input('cpf'))]);
        }

        // Mesma normalização usada por App\Data\Organization\AddressData —
        // a coluna guarda 8 dígitos, nunca a máscara "00000-000".
        if ($this->filled('address_postal_code')) {
            $this->merge(['address_postal_code' => Document::onlyDigits((string) $this->input('address_postal_code'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user('web')->id);
    }
}
