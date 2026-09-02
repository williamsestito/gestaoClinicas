<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

class InvitationPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function cancel(User $user, Invitation $invitation): bool
    {
        return $this->hasAccess($user, $invitation->organization_id, PermissionKey::UsersInvite);
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->hasAccess($user, $invitation->organization_id, PermissionKey::UsersInvite);
    }

    private function hasAccess(User $user, string $organizationId, PermissionKey $permission): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $isOwner = $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active)
            ->where('is_owner', true)
            ->exists();

        return $isOwner || $this->permissionChecker->can($user, $permission, $organizationId);
    }
}
