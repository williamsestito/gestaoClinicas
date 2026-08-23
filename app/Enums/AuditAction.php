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
    case PrimaryProfessionalSpecialtyChanged = 'primary_professional_specialty_changed';
    case PrimaryProfessionalRegistrationChanged = 'primary_professional_registration_changed';
    case PrimaryProfessionalUnitChanged = 'primary_professional_unit_changed';
    case Copied = 'copied';
    case ConflictDetected = 'conflict_detected';
    case Linked = 'linked';
    case Unlinked = 'unlinked';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';

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
            self::PrimaryProfessionalSpecialtyChanged => 'Troca de especialidade principal do profissional',
            self::PrimaryProfessionalRegistrationChanged => 'Troca de registro profissional principal',
            self::PrimaryProfessionalUnitChanged => 'Troca de unidade principal do profissional',
            self::Copied => 'Cópia de dados',
            self::ConflictDetected => 'Conflito detectado (operação bloqueada)',
            self::Linked => 'Vínculo criado',
            self::Unlinked => 'Vínculo removido',
            self::Viewed => 'Visualizado',
            self::Downloaded => 'Baixado',
        };
    }
}
