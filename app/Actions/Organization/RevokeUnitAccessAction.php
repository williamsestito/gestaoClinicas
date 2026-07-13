<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\UnitMembership;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\OwnershipGuard;
use Illuminate\Validation\ValidationException;

/**
 * Revoga (logicamente) o acesso de um membro a uma unidade. Nunca permite
 * remover o acesso do último proprietário ativo à unidade matriz sem
 * substituição.
 */
class RevokeUnitAccessAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(UnitMembership $unitMembership): UnitMembership
    {
        $unitMembership->loadMissing(['unit', 'organizationMembership']);

        if ($unitMembership->unit->is_headquarters && OwnershipGuard::isSoleActiveOwner($unitMembership->organizationMembership)) {
            throw ValidationException::withMessages([
                'unit_membership' => 'Não é possível remover o acesso do último proprietário ativo à unidade matriz.',
            ]);
        }

        $previousStatus = $unitMembership->status;

        $unitMembership->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $unitMembership,
            before: ['status' => $previousStatus->value],
            after: ['status' => RecordStatus::Inactive->value],
            organization: $unitMembership->unit->organization,
            unit: $unitMembership->unit,
        );

        return $unitMembership;
    }
}
