<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Pacientes são escopados por organização, não por unidade (ver
 * docs/modules/patients.md). Diferente de ProfessionalPolicy::viewAny(),
 * aqui a visualização exige explicitamente `PatientsView`/`PatientsManage`
 * (ou o acesso próprio do profissional vinculado) — dado pessoal sensível
 * não é visível a qualquer membro ativo só por estar na organização.
 */
class PatientPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        return $this->hasBroadAccess($user, $organization->id)
            || $this->permissionChecker->can($user, PermissionKey::PatientsView, $organization->id);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id)
            || $this->permissionChecker->can($user, PermissionKey::PatientsView, $patient->organization_id)
            || $this->hasOwnAccess($user, $patient);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function activate(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function deactivate(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function restore(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function manageResponsibles(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function manageEmergencyContacts(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    private function hasBroadAccess(User $user, string $organizationId): bool
    {
        return $this->hasActiveMembership($user, $organizationId, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::PatientsManage, $organizationId);
    }

    /**
     * Autoatendimento do profissional vinculado como `primary_professional_id`:
     * só leitura, nunca gestão. Mesmas checagens de
     * ProfessionalPolicy::hasOwnAccess() — o vínculo por si só nunca basta.
     */
    private function hasOwnAccess(User $user, Patient $patient): bool
    {
        if ($patient->primary_professional_id === null) {
            return false;
        }

        $professional = Professional::query()->find($patient->primary_professional_id);

        if (! $professional || $professional->user_id === null || $professional->user_id !== $user->id) {
            return false;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $patient->organization_id)) {
            return false;
        }

        return $this->permissionChecker->can($user, PermissionKey::PatientsViewOwn, $patient->organization_id);
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
