<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Autorização para a página "Usuários": convidar, editar papel/unidades,
 * ativar/inativar. O proprietário e o administrador técnico sempre têm
 * acesso total; demais usuários dependem das permissões do papel
 * atribuído (ver App\Support\Authorization\PermissionChecker).
 */
class OrganizationMembershipPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    /**
     * Chamado de duas formas: pela navegação do Filament, sem organização
     * (`Gate::authorize('viewAny', OrganizationMembership::class)` — já
     * protegida por `canAccessPanel`, exige apenas is_platform_admin), e
     * pela tela "Usuários" do sistema operacional, com a organização
     * ativa (`can('viewAny', [OrganizationMembership::class, $org])`).
     */
    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        return $this->hasAccess($user, $organization->id, PermissionKey::UsersView);
    }

    public function invite(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization->id, PermissionKey::UsersInvite);
    }

    public function update(User $user, OrganizationMembership $membership): bool
    {
        return $this->hasAccess($user, $membership->organization_id, PermissionKey::UsersUpdate);
    }

    public function assignRoles(User $user, OrganizationMembership $membership): bool
    {
        return $this->hasAccess($user, $membership->organization_id, PermissionKey::UsersAssignRoles);
    }

    public function assignUnits(User $user, OrganizationMembership $membership): bool
    {
        return $this->hasAccess($user, $membership->organization_id, PermissionKey::UsersAssignUnits);
    }

    public function activate(User $user, OrganizationMembership $membership): bool
    {
        return $this->hasAccess($user, $membership->organization_id, PermissionKey::UsersActivate);
    }

    public function deactivate(User $user, OrganizationMembership $membership): bool
    {
        return $this->hasAccess($user, $membership->organization_id, PermissionKey::UsersDeactivate);
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
