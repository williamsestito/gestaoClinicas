<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case PrimaryLegalEntityChanged = 'primary_legal_entity_changed';
    case HeadquartersChanged = 'headquarters_changed';
    case OrganizationContextSwitched = 'organization_context_switched';
    case UnitContextSwitched = 'unit_context_switched';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Criado',
            self::Updated => 'Atualizado',
            self::Activated => 'Ativado',
            self::Deactivated => 'Inativado',
            self::Deleted => 'Excluído (lógico)',
            self::Restored => 'Restaurado',
            self::PrimaryLegalEntityChanged => 'Troca de entidade legal principal',
            self::HeadquartersChanged => 'Troca de unidade matriz',
            self::OrganizationContextSwitched => 'Troca de organização ativa',
            self::UnitContextSwitched => 'Troca de unidade ativa',
        };
    }
}
