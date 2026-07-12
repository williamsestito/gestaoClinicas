<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SetActiveOrganizationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Request $request, User $user, Organization $organization): void
    {
        $membership = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'organization' => 'Você não tem acesso a esta organização.',
            ]);
        }

        $request->session()->put('active_organization_id', $organization->id);
        $request->session()->forget('active_unit_id');

        $this->auditLogger->log(
            AuditAction::OrganizationContextSwitched,
            auditable: $organization,
            after: ['organization_id' => $organization->id],
            organization: $organization,
        );
    }
}
