<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * (criar/editar/excluir/restaurar) exige o proprietário ou a permissão
 * granular correspondente via papel atribuído. O número de registro é
 * tratado como dado sensível na auditoria (ver App\Support\Auditing\AuditLogger).
 */
class ProfessionalRegistrationPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $organization->id);
    }

    public function update(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id);
    }

    public function activate(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id);
    }

    public function deactivate(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id);
    }

    public function setPrimary(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id);
    }

    /** Ver o número completo (não mascarado) do registro. */
    public function viewSensitive(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsViewSensitive, $registration->organization_id);
    }

    public function delete(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id);
    }

    public function restore(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id);
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
