<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalWorkingHour;
use App\Support\Auditing\AuditLogger;

class DeactivateProfessionalWorkingHourAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalWorkingHour $workingHour): ProfessionalWorkingHour
    {
        $workingHour->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $workingHour,
            before: ['status' => RecordStatus::Active->value],
            after: ['status' => RecordStatus::Inactive->value],
            organization: $workingHour->organization,
        );

        return $workingHour;
    }
}
