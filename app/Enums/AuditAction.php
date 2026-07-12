<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case OrganizationContextSwitched = 'organization_context_switched';
    case UnitContextSwitched = 'unit_context_switched';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Criado',
            self::Updated => 'Atualizado',
            self::Activated => 'Ativado',
            self::Deactivated => 'Inativado',
            self::OrganizationContextSwitched => 'Troca de organização ativa',
            self::UnitContextSwitched => 'Troca de unidade ativa',
        };
    }
}
