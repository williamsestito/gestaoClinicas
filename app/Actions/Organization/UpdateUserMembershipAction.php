<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Support\Auditing\AuditLogger;

class UpdateUserMembershipAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(OrganizationMembership $membership, ?Role $role, ?string $adminNote): OrganizationMembership
    {
        $before = ['role_id' => $membership->role_id, 'admin_note' => $membership->admin_note];

        $membership->update([
            'role_id' => $role?->id,
            'admin_note' => $adminNote,
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $membership,
            before: $before,
            after: ['role_id' => $membership->role_id, 'admin_note' => $membership->admin_note],
            organization: $membership->organization,
        );

        return $membership;
    }
}
