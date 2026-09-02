<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * (criar/editar/excluir/restaurar) exige o proprietário ou a permissão
 * granular correspondente via papel atribuído. O número de registro é
 * tratado como dado sensível na auditoria (ver App\Support\Auditing\AuditLogger).
 *
 * Autoatendimento — dados próprios: todas as ações de gestão também
 * aceitam o próprio profissional vinculado gerindo seus PRÓPRIOS
 * registros, sem exigir nenhuma permissão granular extra (mesmo racional
 * de `ProfessionalPolicy::update()` — corrigir/completar o próprio
 * cadastro básico é esperado de qualquer profissional autoatendido).
 * `viewSensitive()` também: ver o número completo do próprio registro
 * nunca deveria depender de uma permissão administrativa.
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

    public function create(User $user, Organization $organization, ?Professional $professional = null): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $organization->id)
            || ($professional !== null && $this->isSelfProfessional($user, $professional));
    }

    public function update(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    public function activate(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    public function deactivate(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    public function setPrimary(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    /** Ver o número completo (não mascarado) do registro. */
    public function viewSensitive(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsViewSensitive, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    public function delete(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    public function restore(User $user, ProfessionalRegistration $registration): bool
    {
        return $this->hasActiveMembership($user, $registration->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalRegistrationsManage, $registration->organization_id)
            || $this->isSelfProfessional($user, $registration->professional);
    }

    private function isSelfProfessional(User $user, Professional $professional): bool
    {
        if ($professional->user_id === null || $professional->user_id !== $user->id) {
            return false;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        return $this->hasActiveMembership($user, $professional->organization_id);
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
