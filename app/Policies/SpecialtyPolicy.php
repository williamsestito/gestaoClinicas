<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * (criar/editar/excluir/restaurar) exige o proprietário ou a permissão
 * granular correspondente via papel atribuído (ver
 * App\Support\Authorization\PermissionChecker). Administrador técnico tem
 * acesso total.
 */
class SpecialtyPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, Specialty $specialty): bool
    {
        return $this->hasActiveMembership($user, $specialty->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::SpecialtiesManage, $organization->id);
    }

    public function update(User $user, Specialty $specialty): bool
    {
        return $this->hasActiveMembership($user, $specialty->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::SpecialtiesManage, $specialty->organization_id);
    }

    public function activate(User $user, Specialty $specialty): bool
    {
        return $this->hasActiveMembership($user, $specialty->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::SpecialtiesManage, $specialty->organization_id);
    }

    public function deactivate(User $user, Specialty $specialty): bool
    {
        return $this->hasActiveMembership($user, $specialty->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::SpecialtiesManage, $specialty->organization_id);
    }

    public function delete(User $user, Specialty $specialty): bool
    {
        return $this->hasActiveMembership($user, $specialty->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::SpecialtiesManage, $specialty->organization_id);
    }

    public function restore(User $user, Specialty $specialty): bool
    {
        return $this->hasActiveMembership($user, $specialty->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::SpecialtiesManage, $specialty->organization_id);
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
