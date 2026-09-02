<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Concede acesso de um membro da organização a uma unidade. Se já existir
 * um vínculo revogado, reativa-o em vez de duplicar o registro.
 */
class GrantUnitAccessAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(OrganizationMembership $membership, Unit $unit, bool $isManager = false): UnitMembership
    {
        if ($unit->organization_id !== $membership->organization_id) {
            throw ValidationException::withMessages([
                'unit' => 'A unidade não pertence à mesma organização do vínculo informado.',
            ]);
        }

        $unitMembership = $membership->unitMemberships()->where('unit_id', $unit->id)->first();

        if ($unitMembership) {
            $unitMembership->update(['status' => RecordStatus::Active, 'is_manager' => $isManager]);
        } else {
            $unitMembership = $membership->unitMemberships()->create([
                'unit_id' => $unit->id,
                'status' => RecordStatus::Active,
                'is_manager' => $isManager,
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $unitMembership,
            after: ['unit_id' => $unit->id, 'organization_membership_id' => $membership->id],
            organization: $unit->organization,
            unit: $unit,
        );

        return $unitMembership;
    }
}
