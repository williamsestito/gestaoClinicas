<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Solicitações de agendamento (leads) enviadas pela landing pública. Mesmo
 * padrão de App\Policies\SiteSettingPolicy, mas com permissões próprias
 * (site.appointments.*) — ver um profissional de recepção que precisa
 * tratar os leads sem necessariamente poder editar o conteúdo do site.
 */
class AppointmentRequestPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SiteAppointmentsView);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasAccess($user, $organization, PermissionKey::SiteAppointmentsManage);
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
