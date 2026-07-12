<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\LegalEntityType;
use App\Support\Documents\Document;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um CPF ou CNPJ (com ou sem máscara) de acordo com o tipo
 * informado em outro campo do mesmo request.
 */
final readonly class CpfCnpjRule implements ValidationRule
{
    public function __construct(private LegalEntityType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Document::isValid($this->type, $value)) {
            $fail($this->type === LegalEntityType::Individual
                ? 'O CPF informado é inválido.'
                : 'O CNPJ informado é inválido.');
        }
    }
}
