<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de uma entrada na lista de espera (Etapa 3.3 do roadmap).
 * Sem motor de notificação automática — a recepção consulta e converte à
 * mão (App\Actions\Organization\CreateAppointmentAction::convertSourceWaitlistEntry()).
 */
enum WaitlistEntryStatus: string
{
    case Waiting = 'waiting';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Aguardando',
            self::Converted => 'Convertido em agendamento',
            self::Cancelled => 'Cancelado',
        };
    }
}
