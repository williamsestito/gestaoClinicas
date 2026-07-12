<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;

class ChangeUnitStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Unit $unit, RecordStatus $status): Unit
    {
        $previousStatus = $unit->status;

        $unit->update(['status' => $status]);

        $this->auditLogger->log(
            $status === RecordStatus::Active ? AuditAction::Activated : AuditAction::Deactivated,
            auditable: $unit,
            before: ['status' => $previousStatus->value],
            after: ['status' => $status->value],
            organization: $unit->organization,
            unit: $unit,
        );

        return $unit;
    }
}
