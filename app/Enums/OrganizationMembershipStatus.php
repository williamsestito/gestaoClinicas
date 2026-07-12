<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Invited => 'Convidado',
            self::Inactive => 'Inativo',
        };
    }
}
