<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de uma solicitação de agendamento enviada pela landing
 * pública. Não existe agenda/disponibilidade real no sistema ainda — a
 * clínica sempre confirma manualmente por telefone/WhatsApp/e-mail antes de
 * mudar o status para Scheduled.
 */
enum AppointmentRequestStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando contato',
            self::Contacted => 'Contato realizado',
            self::Scheduled => 'Agendado',
            self::Cancelled => 'Cancelado',
        };
    }
}
