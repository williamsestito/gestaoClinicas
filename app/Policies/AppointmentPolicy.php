<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Agendamentos são escopados por organização. `manageStatus` cobre o
 * profissional vinculado ao agendamento (via `professional.user_id`) —
 * gestão própria é limitada a transições de status do próprio atendimento
 * (check-in/início/conclusão/não comparecimento), nunca criar/reagendar/
 * cancelar (exclusivo de quem tem `appointments.manage`).
 */
class AppointmentPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        return $this->hasBroadAccess($user, $organization->id)
            || $this->permissionChecker->can($user, PermissionKey::AppointmentsView, $organization->id);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->hasBroadAccess($user, $appointment->organization_id)
            || $this->permissionChecker->can($user, PermissionKey::AppointmentsView, $appointment->organization_id)
            || $this->hasOwnAccess($user, $appointment, PermissionKey::AppointmentsViewOwn);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id);
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $this->hasBroadAccess($user, $appointment->organization_id);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->hasBroadAccess($user, $appointment->organization_id);
    }

    public function confirm(User $user, Appointment $appointment): bool
    {
        return $this->hasBroadAccess($user, $appointment->organization_id);
    }

    public function proposeAlternateTime(User $user, Appointment $appointment): bool
    {
        return $this->hasBroadAccess($user, $appointment->organization_id);
    }

    public function manageStatus(User $user, Appointment $appointment): bool
    {
        return $this->hasBroadAccess($user, $appointment->organization_id)
            || $this->hasOwnAccess($user, $appointment, PermissionKey::AppointmentsManageOwn);
    }

    private function hasBroadAccess(User $user, string $organizationId): bool
    {
        return $this->hasActiveMembership($user, $organizationId, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::AppointmentsManage, $organizationId);
    }

    private function hasOwnAccess(User $user, Appointment $appointment, PermissionKey $permission): bool
    {
        $professional = $appointment->professional;

        if (! $professional || $professional->user_id === null || $professional->user_id !== $user->id) {
            return false;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $appointment->organization_id)) {
            return false;
        }

        return $this->permissionChecker->can($user, $permission, $appointment->organization_id);
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
