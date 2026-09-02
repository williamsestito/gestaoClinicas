<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Gestão de unidades é liberada para o proprietário da organização (acesso
 * total, sempre) ou para quem tiver a permissão granular correspondente
 * via papel atribuído (ver App\Support\Authorization\PermissionChecker).
 * Qualquer membro ativo pode visualizar. O administrador técnico
 * (`is_platform_admin`) tem acesso total em qualquer organização.
 */
class UnitPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function view(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsCreate, $organization->id);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsUpdate, $unit->organization_id);
    }

    public function activate(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsActivate, $unit->organization_id);
    }

    public function deactivate(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsDeactivate, $unit->organization_id);
    }

    public function setHeadquarters(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsSetHeadquarters, $unit->organization_id);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsDelete, $unit->organization_id);
    }

    public function restore(User $user, Unit $unit): bool
    {
        return $this->hasActiveMembership($user, $unit->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::UnitsRestore, $unit->organization_id);
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
