<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalService;
use App\Support\Auditing\AuditLogger;

class DeactivateProfessionalServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalService $link): ProfessionalService
    {
        $previousStatus = $link->status;

        $link->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $link,
            before: ['status' => $previousStatus->value],
            after: ['status' => $link->status->value],
            organization: $link->organization,
        );

        return $link;
    }
}
