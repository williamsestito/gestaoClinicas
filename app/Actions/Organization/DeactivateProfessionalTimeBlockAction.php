<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalTimeBlock;
use App\Support\Auditing\AuditLogger;

class DeactivateProfessionalTimeBlockAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalTimeBlock $timeBlock): ProfessionalTimeBlock
    {
        $timeBlock->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $timeBlock,
            before: ['status' => RecordStatus::Active->value],
            after: ['status' => RecordStatus::Inactive->value],
            organization: $timeBlock->organization,
        );

        return $timeBlock;
    }
}
