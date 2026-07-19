<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que o domínio oficial é um hostname puro — sem protocolo,
 * caminho, porta, parâmetros ou caracteres perigosos. Usado para montar
 * URLs canônicas (App\Support\Seo\CanonicalUrlResolver); nunca deve ser
 * concatenado sem essa validação prévia.
 */
final readonly class OfficialDomainRule implements ValidationRule
{
    private const HOSTNAME_PATTERN = '/^(?!-)(?!.*--)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63}(?<!-))+$/i';

    /**
     * O domínio é opcional — sem valor, não há nada a validar (ambiente
     * local cai no fallback de APP_URL). Quando um valor é informado,
     * porém, precisa ser um hostname puro e válido.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Informe um domínio válido.');

            return;
        }

        if (str_contains($value, '://')) {
            $fail('Informe apenas o domínio, sem "http://" ou "https://".');

            return;
        }

        if (str_contains($value, '/') || str_contains($value, '?') || str_contains($value, '#')) {
            $fail('O domínio não pode conter caminhos ou parâmetros — apenas o nome do domínio.');

            return;
        }

        if (str_contains($value, ' ') || preg_match('/[<>"\'`]/', $value) === 1) {
            $fail('O domínio contém caracteres inválidos.');

            return;
        }

        if (strlen($value) > 253 || preg_match(self::HOSTNAME_PATTERN, $value) !== 1) {
            $fail('Informe um domínio válido, como "clinicaexemplo.com.br".');
        }
    }
}
