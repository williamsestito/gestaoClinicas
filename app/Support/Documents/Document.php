<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\LegalEntityType;
use InvalidArgumentException;

/**
 * Value Object imutável para CPF/CNPJ. Sempre normaliza (remove máscara) e
 * valida os dígitos verificadores na construção — não é possível existir
 * uma instância com um documento inválido.
 */
final readonly class Document
{
    private function __construct(
        public string $digits,
        public LegalEntityType $type,
    ) {}

    public static function fromCpf(string $value): self
    {
        $digits = self::onlyDigits($value);

        if (strlen($digits) !== 11 || ! self::isValidCpf($digits)) {
            throw new InvalidArgumentException('CPF inválido.');
        }

        return new self($digits, LegalEntityType::Individual);
    }

    public static function fromCnpj(string $value): self
    {
        $digits = self::onlyDigits($value);

        if (strlen($digits) !== 14 || ! self::isValidCnpj($digits)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }

        return new self($digits, LegalEntityType::Company);
    }

    public static function fromType(LegalEntityType $type, string $value): self
    {
        return match ($type) {
            LegalEntityType::Individual => self::fromCpf($value),
            LegalEntityType::Company => self::fromCnpj($value),
        };
    }

    public static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function isValid(LegalEntityType $type, string $value): bool
    {
        try {
            self::fromType($type, $value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function formatted(): string
    {
        return $this->type === LegalEntityType::Individual
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->digits)
            : preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $this->digits);
    }

    /**
     * Versão mascarada, segura para logs/auditoria (nunca expor o
     * documento completo).
     */
    public function masked(): string
    {
        $length = strlen($this->digits);

        return str_repeat('*', $length - 2).substr($this->digits, -2);
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $cpf[$i] * (($position + 1) - $i);
            }

            $remainder = ($sum * 10) % 11;
            $expectedDigit = $remainder === 10 ? 0 : $remainder;

            if ((int) $cpf[$position] !== $expectedDigit) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $weightsFirstDigit = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsSecondDigit = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([$weightsFirstDigit, $weightsSecondDigit] as $position => $weights) {
            $length = 12 + $position;
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$i];
            }

            $remainder = $sum % 11;
            $expectedDigit = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $cnpj[$length] !== $expectedDigit) {
                return false;
            }
        }

        return true;
    }
}
