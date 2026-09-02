<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * O proprietário (organization_membership.is_owner=true) sempre pode
 * alterar a organização; quem tiver a permissão `organization.update` via
 * papel atribuído também pode (ver
 * App\Support\Authorization\PermissionChecker). Qualquer membro ativo
 * pode visualizá-la. Nenhuma exclusão física é permitida.
 */
class OrganizationPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::OrganizationUpdate, $organization->id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }

    private function hasActiveMembership(User $user, Organization $organization, bool $requireOwner = false): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $query = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active);

        if ($requireOwner) {
            $query->where('is_owner', true);
        }

        return $query->exists();
    }
}
