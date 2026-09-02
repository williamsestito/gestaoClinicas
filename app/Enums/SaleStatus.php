<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::PendingApproval => 'Aguardando aprovação',
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Cancelada',
        };
    }
}
