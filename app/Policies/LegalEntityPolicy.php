<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Gestão da entidade legal é liberada para o proprietário da organização
 * (acesso total, sempre) ou para quem tiver a permissão granular
 * correspondente via papel atribuído (ver
 * App\Support\Authorization\PermissionChecker). Qualquer membro ativo
 * pode visualizar. O administrador técnico tem acesso total.
 */
class LegalEntityPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function view(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id);
    }

    public function update(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::LegalEntitiesUpdate, $legalEntity->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::LegalEntitiesCreate, $organization->id);
    }

    public function setPrimary(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::LegalEntitiesSetPrimary, $legalEntity->organization_id);
    }

    public function delete(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::LegalEntitiesDelete, $legalEntity->organization_id);
    }

    public function restore(User $user, LegalEntity $legalEntity): bool
    {
        return $this->hasActiveMembership($user, $legalEntity->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::LegalEntitiesRestore, $legalEntity->organization_id);
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
