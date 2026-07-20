<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * `SiteSetting` é um singleton global (não tem organization_id — só há
 * uma instalação, uma clínica), mas a permissão para vê-lo/editá-lo ainda
 * é checada contra o vínculo do usuário com a organização ativa.
 */
class SiteSettingPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SiteView);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SiteUpdate);
    }

    public function publish(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SitePublish);
    }

    public function viewSeo(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SeoView);
    }

    public function updateSeo(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SeoUpdate);
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
