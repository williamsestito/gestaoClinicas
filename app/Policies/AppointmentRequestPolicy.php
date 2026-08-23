<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\AppointmentRequest;
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

    /**
     * Converter esta solicitação específica num Appointment real (ver
     * App\Actions\Organization\CreateAppointmentAction, chamada por
     * AppointmentController::create()/store() quando recebe
     * `appointment_request_id`). Quem tem `site.appointments.manage`/
     * `appointments.manage` já autoriza por outro caminho
     * (AppointmentPolicy::create()); este método cobre só o profissional
     * convertendo o PRÓPRIO lead, nunca o de outro profissional — mesmo
     * padrão de UpdateOwnAppointmentRequestStatusRequest::authorize().
     *
     * O argumento precisa ser um App\Models\AppointmentRequest (não
     * App\Models\Appointment): o Gate do Laravel resolve a policy pela
     * classe do model passado a `can()`/`authorize()`, nunca por qual
     * classe de Policy declara o método.
     */
    public function createFromOwnRequest(User $user, AppointmentRequest $sourceRequest): bool
    {
        if ($sourceRequest->professional_id === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $sourceRequest->organization_id)) {
            return false;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        return $user->professionals()
            ->where('status', RecordStatus::Active)
            ->whereKey($sourceRequest->professional_id)
            ->exists()
            && $this->permissionChecker->can($user, PermissionKey::AppointmentsManageOwn, $sourceRequest->organization_id);
    }

    private function hasActiveMembership(User $user, string $organizationId): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active)
            ->exists();
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
