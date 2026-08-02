<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação de vigência calculada de um vínculo profissional/unidade a
 * partir de `starts_on`/`ends_on` e da data corrente — nunca persistida,
 * sempre derivada (ver App\Models\ProfessionalUnit::vigencyStatus()).
 * Independente do `status` (ativo/inativo) do próprio vínculo.
 */
enum ProfessionalUnitVigencyStatus: string
{
    case Scheduled = 'scheduled';
    case InEffect = 'in_effect';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::InEffect => 'Vigente',
            self::Ended => 'Encerrado',
        };
    }
}
