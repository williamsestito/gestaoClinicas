<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\SharedResource;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * (criar/editar/ativar/inativar/excluir/restaurar) exige o proprietário ou
 * a permissão granular `resources.manage`. Mesmo formato de
 * App\Policies\SpecialtyPolicy.
 */
class SharedResourcePolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, SharedResource $resource): bool
    {
        return $this->hasActiveMembership($user, $resource->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id);
    }

    public function update(User $user, SharedResource $resource): bool
    {
        return $this->hasBroadAccess($user, $resource->organization_id);
    }

    public function activate(User $user, SharedResource $resource): bool
    {
        return $this->hasBroadAccess($user, $resource->organization_id);
    }

    public function deactivate(User $user, SharedResource $resource): bool
    {
        return $this->hasBroadAccess($user, $resource->organization_id);
    }

    public function delete(User $user, SharedResource $resource): bool
    {
        return $this->hasBroadAccess($user, $resource->organization_id);
    }

    public function restore(User $user, SharedResource $resource): bool
    {
        return $this->hasBroadAccess($user, $resource->organization_id);
    }

    private function hasBroadAccess(User $user, string $organizationId): bool
    {
        return $this->hasActiveMembership($user, $organizationId, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ResourcesManage, $organizationId);
    }

    private function hasActiveMembership(User $user, string $organizationId, bool $requireOwner = false): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $query = $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active);

        if ($requireOwner) {
            $query->where('is_owner', true);
        }

        return $query->exists();
    }
}
