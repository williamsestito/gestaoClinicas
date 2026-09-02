<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\UnitMembership;
use App\Support\Auditing\AuditLogger;

class RestoreUnitAccessAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(UnitMembership $unitMembership): UnitMembership
    {
        $previousStatus = $unitMembership->status;

        $unitMembership->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $unitMembership,
            before: ['status' => $previousStatus->value],
            after: ['status' => RecordStatus::Active->value],
            organization: $unitMembership->unit->organization,
            unit: $unitMembership->unit,
        );

        return $unitMembership;
    }
}
