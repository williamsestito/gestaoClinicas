<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida completo de um agendamento real (seção 8.4 do documento de
 * visão). Nesta etapa (3.1), a única forma de criar é pelo staff, que já
 * validou o horário — a criação sempre entra direto em `Confirmed`.
 * `Requested`/`AwaitingConfirmation` existem para quando o booking público
 * (Etapa 3.2/2.2) precisar de uma passagem por análise antes da
 * confirmação — não são alcançáveis por nenhum fluxo desta etapa.
 */
enum AppointmentStatus: string
{
    case Requested = 'requested';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitado',
            self::AwaitingConfirmation => 'Aguardando confirmação',
            self::Confirmed => 'Confirmado',
            self::CheckedIn => 'Check-in realizado',
            self::InProgress => 'Em atendimento',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
            self::NoShow => 'Não compareceu',
        };
    }

    /** Estados finais — nenhuma transição de status é permitida a partir daqui. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::NoShow => true,
            default => false,
        };
    }
}
