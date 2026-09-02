<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\OwnershipGuard;
use Illuminate\Validation\ValidationException;

/**
 * Nunca permite desativar o último proprietário ativo de uma organização —
 * ela sempre precisa de pelo menos um.
 */
class DeactivateOrganizationMembershipAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(OrganizationMembership $membership): OrganizationMembership
    {
        if (OwnershipGuard::isSoleActiveOwner($membership)) {
            throw ValidationException::withMessages([
                'membership' => 'Não é possível inativar o último proprietário ativo da clínica.',
            ]);
        }

        $previousStatus = $membership->status;

        $membership->update(['status' => OrganizationMembershipStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $membership,
            before: ['status' => $previousStatus->value],
            after: ['status' => OrganizationMembershipStatus::Inactive->value],
            organization: $membership->organization,
        );

        return $membership;
    }
}
