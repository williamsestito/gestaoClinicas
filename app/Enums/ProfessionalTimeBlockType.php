<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de ausência/bloqueio do profissional. Todos reduzem a
 * disponibilidade regular — nenhum cria disponibilidade adicional.
 */
enum ProfessionalTimeBlockType: string
{
    case Vacation = 'vacation';
    case DayOff = 'day_off';
    case Absence = 'absence';
    case AdministrativeBlock = 'administrative_block';
    case ExternalEvent = 'external_event';
    case PartialUnavailability = 'partial_unavailability';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Férias',
            self::DayOff => 'Folga',
            self::Absence => 'Ausência',
            self::AdministrativeBlock => 'Bloqueio administrativo',
            self::ExternalEvent => 'Evento externo',
            self::PartialUnavailability => 'Indisponibilidade parcial',
        };
    }
}
