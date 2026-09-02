<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;
use App\Support\Auditing\AuditLogger;

class ActivateOrganizationMembershipAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(OrganizationMembership $membership): OrganizationMembership
    {
        $previousStatus = $membership->status;

        $membership->update(['status' => OrganizationMembershipStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $membership,
            before: ['status' => $previousStatus->value],
            after: ['status' => OrganizationMembershipStatus::Active->value],
            organization: $membership->organization,
        );

        return $membership;
    }
}
