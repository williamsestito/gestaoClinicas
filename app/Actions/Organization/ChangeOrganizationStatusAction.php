<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Support\Auditing\AuditLogger;

class ChangeOrganizationStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Organization $organization, OrganizationStatus $status): Organization
    {
        $previousStatus = $organization->status;

        $organization->update(['status' => $status]);

        $this->auditLogger->log(
            $status === OrganizationStatus::Active ? AuditAction::Activated : AuditAction::Deactivated,
            auditable: $organization,
            before: ['status' => $previousStatus->value],
            after: ['status' => $status->value],
            organization: $organization,
        );

        return $organization;
    }
}
