<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * do cadastro exige o proprietário ou a permissão granular correspondente
 * via papel atribuído. Gerir vínculos com unidades/serviços tem permissões
 * próprias (`ProfessionalsManageUnits`/`ProfessionalsManageServices`) —
 * vincular um profissional a um `User` nunca é autorizado por esta policy
 * nem concede permissões: isso depende exclusivamente de
 * OrganizationMembership/Role (ver App\Support\Authorization\PermissionChecker).
 */
class ProfessionalPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $organization->id);
    }

    public function update(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function activate(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function deactivate(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function linkUser(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function delete(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function restore(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function manageUnits(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManageUnits, $professional->organization_id);
    }

    public function manageServices(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManageServices, $professional->organization_id);
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
