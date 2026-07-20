<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Papéis de sistema (`is_system = true`) nunca podem ser excluídos. O
 * papel "Proprietário" nunca pode ter suas permissões alteradas — seu
 * acesso total já vem de `organization_membership.is_owner`, independente
 * do que estivesse listado aqui; permitir editá-lo só confundiria quem
 * está administrando (pareceria possível restringir o proprietário, o
 * que não é verdade).
 */
class RolePolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization->id, PermissionKey::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->hasAccess($user, $role->organization_id, PermissionKey::RolesView);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization->id, PermissionKey::RolesCreate);
    }

    public function update(User $user, Role $role): bool
    {
        if ($this->isOwnerRole($role)) {
            return false;
        }

        return $this->hasAccess($user, $role->organization_id, PermissionKey::RolesUpdate);
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $this->hasAccess($user, $role->organization_id, PermissionKey::RolesDelete);
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        if ($this->isOwnerRole($role)) {
            return false;
        }

        return $this->hasAccess($user, $role->organization_id, PermissionKey::RolesAssignPermissions);
    }

    private function isOwnerRole(Role $role): bool
    {
        return $role->is_system && $role->slug === SystemRole::Owner->value;
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
