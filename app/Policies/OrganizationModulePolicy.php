<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * `OrganizationModule` é escopado por organização, mas a tela é uma
 * configuração única (sem `{module}` na URL) — por isso a policy checa a
 * permissão direto contra a `Organization` ativa, mesmo padrão de
 * `SiteSettingPolicy`.
 */
class OrganizationModulePolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::ModulesView);
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::ModulesManage);
    }

    private function hasAccess(User $user, Organization $organization, PermissionKey $permission): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $isOwner = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->where('is_owner', true)
            ->exists();

        return $isOwner || $this->permissionChecker->can($user, $permission, $organization->id);
    }
}
