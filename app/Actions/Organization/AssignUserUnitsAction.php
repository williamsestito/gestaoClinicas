<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\OrganizationMembership;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Sincroniza as unidades que o vínculo pode acessar e qual delas é a
 * principal. Nunca remove fisicamente vínculos de unidade antigos —
 * apenas os inativa, preservando o histórico.
 */
class AssignUserUnitsAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  Collection<int, string>|array<int, string>  $unitIds */
    public function handle(OrganizationMembership $membership, iterable $unitIds, ?string $primaryUnitId): OrganizationMembership
    {
        $unitIds = collect($unitIds)->unique()->values();
        $existing = $membership->unitMemberships()->get()->keyBy('unit_id');

        foreach ($unitIds as $unitId) {
            $unitMembership = $existing->get($unitId);

            if ($unitMembership) {
                $unitMembership->update([
                    'status' => RecordStatus::Active,
                    'is_primary' => $unitId === $primaryUnitId,
                ]);

                continue;
            }

            $membership->unitMemberships()->create([
                'unit_id' => $unitId,
                'status' => RecordStatus::Active,
                'is_manager' => false,
                'is_primary' => $unitId === $primaryUnitId,
            ]);
        }

        $removedUnitIds = $existing->keys()->diff($unitIds);
        $membership->unitMemberships()
            ->whereIn('unit_id', $removedUnitIds)
            ->update(['status' => RecordStatus::Inactive, 'is_primary' => false]);

        if ($primaryUnitId && ! $unitIds->contains($primaryUnitId)) {
            $primaryUnitId = null;
        }

        $membership->unitMemberships()
            ->where('unit_id', '!=', $primaryUnitId)
            ->update(['is_primary' => false]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $membership,
            after: ['unit_ids' => $unitIds->all(), 'primary_unit_id' => $primaryUnitId],
            organization: $membership->organization,
        );

        return $membership;
    }
}
