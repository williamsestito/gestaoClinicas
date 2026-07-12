<?php

declare(strict_types=1);

namespace App\Enums;

enum LegalEntityType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Pessoa física (CPF)',
            self::Company => 'Pessoa jurídica (CNPJ)',
        };
    }

    public function documentLength(): int
    {
        return match ($this) {
            self::Individual => 11,
            self::Company => 14,
        };
    }
}
