<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação de validade calculada de um registro profissional a partir de
 * `expires_at` e da data corrente do negócio — nunca persistida, sempre
 * derivada (ver App\Models\ProfessionalRegistration::validityStatus()).
 */
enum ProfessionalRegistrationValidityStatus: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case NoExpiration = 'no_expiration';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Válido',
            self::ExpiringSoon => 'Próximo do vencimento',
            self::Expired => 'Vencido',
            self::NoExpiration => 'Sem validade informada',
        };
    }
}
