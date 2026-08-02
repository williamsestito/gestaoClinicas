<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Service;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * (criar/editar/excluir/restaurar) exige o proprietário ou a permissão
 * granular correspondente via papel atribuído (ver
 * App\Support\Authorization\PermissionChecker). Administrador técnico tem
 * acesso total.
 */
class ServicePolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, Service $service): bool
    {
        return $this->hasActiveMembership($user, $service->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ServicesManage, $organization->id);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->hasActiveMembership($user, $service->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ServicesManage, $service->organization_id);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->hasActiveMembership($user, $service->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ServicesManage, $service->organization_id);
    }

    public function restore(User $user, Service $service): bool
    {
        return $this->hasActiveMembership($user, $service->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ServicesManage, $service->organization_id);
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
