<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SetActiveUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Request $request, OrganizationMembership $membership, Unit $unit): void
    {
        if ($unit->organization_id !== $membership->organization_id) {
            throw ValidationException::withMessages([
                'unit' => 'Esta unidade não pertence à organização ativa.',
            ]);
        }

        $unitMembership = $membership->unitMemberships()
            ->where('unit_id', $unit->id)
            ->where('status', RecordStatus::Active)
            ->first();

        if (! $unitMembership) {
            throw ValidationException::withMessages([
                'unit' => 'Você não tem acesso a esta unidade.',
            ]);
        }

        $request->session()->put('active_unit_id', $unit->id);

        $this->auditLogger->log(
            AuditAction::UnitContextSwitched,
            auditable: $unit,
            after: ['unit_id' => $unit->id],
            organization: $unit->organization,
            unit: $unit,
        );
    }
}
