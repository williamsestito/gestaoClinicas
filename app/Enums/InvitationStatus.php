<?php

declare(strict_types=1);

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Accepted => 'Aceito',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }
}
