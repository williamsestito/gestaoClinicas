<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * Fonte única de verdade para "este usuário pode X nesta organização?".
 * Ordem de checagem: administrador técnico (acesso total) → proprietário
 * (acesso total à sua organização) → papel atribuído ao vínculo ativo.
 * Nunca confia em nada vindo do frontend — sempre reconsulta o vínculo
 * real no banco.
 */
class PermissionChecker
{
    public function can(User $user, PermissionKey $permission, string $organizationId): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $membership = $this->activeMembership($user, $organizationId);

        if (! $membership) {
            return false;
        }

        if ($membership->is_owner) {
            return true;
        }

        if (! $membership->role_id) {
            return false;
        }

        return $membership->role->permissions()->where('key', $permission->value)->exists();
    }

    /**
     * Todas as chaves de permissão que o usuário efetivamente possui na
     * organização — usado para refletir a navegação/UI no frontend.
     * Autorização real de escrita nunca deve depender só disto; sempre
     * revalidar via `can()`/Policy no backend.
     *
     * @return array<int, string>
     */
    public function effectivePermissions(User $user, string $organizationId): array
    {
        if ($user->is_platform_admin) {
            return array_map(fn (PermissionKey $key) => $key->value, PermissionKey::cases());
        }

        $membership = $this->activeMembership($user, $organizationId);

        if (! $membership) {
            return [];
        }

        if ($membership->is_owner) {
            return array_map(fn (PermissionKey $key) => $key->value, PermissionKey::cases());
        }

        if (! $membership->role_id) {
            return [];
        }

        return $membership->role->permissions()->pluck('key')->all();
    }

    private function activeMembership(User $user, string $organizationId): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->with('role.permissions')
            ->first();
    }
}
