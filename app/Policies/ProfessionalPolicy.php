<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\Unit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Visualização liberada para qualquer membro ativo da organização; gestão
 * do cadastro exige o proprietário ou a permissão granular correspondente
 * via papel atribuído. Gerir vínculos com unidades/serviços tem permissões
 * próprias (`ProfessionalsManageUnits`/`ProfessionalsManageServices`) —
 * vincular um profissional a um `User` nunca é autorizado por esta policy
 * nem concede permissões: isso depende exclusivamente de
 * OrganizationMembership/Role (ver App\Support\Authorization\PermissionChecker).
 *
 * Autoatendimento ("Minha agenda"): `manageAvailability()`/
 * `manageTimeBlocks()` também aceitam o próprio profissional vinculado ao
 * usuário autenticado, desde que ele tenha a permissão granular
 * correspondente (`ProfessionalOwnAvailabilityManage`/
 * `ProfessionalOwnTimeBlocksManage`) — ver `hasOwnAccess()`.
 */
class ProfessionalPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    /**
     * Chamado de duas formas: pela navegação do Filament, sem organização
     * (protegida por `canAccessPanel`, exige apenas is_platform_admin), e
     * pela tela settings/professionals (Inertia), com a organização ativa.
     */
    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $organization->id);
    }

    public function update(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function activate(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function deactivate(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function linkUser(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function delete(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function restore(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id);
    }

    public function manageSpecialties(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManageSpecialties, $professional->organization_id);
    }

    public function manageUnits(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManageUnits, $professional->organization_id);
    }

    public function manageServices(User $user, Professional $professional): bool
    {
        return $this->hasActiveMembership($user, $professional->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManageServices, $professional->organization_id);
    }

    /**
     * Gestão de jornada e disponibilidade. Diferente das demais permissões
     * desta Policy (sempre org-wide), esta é escopada por unidade quando o
     * acesso vem de `ProfessionalAvailabilityManage` (ex.: Gerente de
     * unidade) em vez do acesso amplo de proprietário/`ProfessionalsManage`:
     * nesse caso, exige `UnitMembership.is_manager = true` ativo para a
     * unidade específica do intervalo de jornada. `$unit` é obrigatório
     * porque toda jornada pertence a uma unidade (via professional_unit).
     */
    public function manageAvailability(User $user, Professional $professional, Unit $unit): bool
    {
        return $this->hasBroadProfessionalAccess($user, $professional->organization_id)
            || $this->hasUnitScopedAccess($user, $professional->organization_id, $unit, PermissionKey::ProfessionalAvailabilityManage)
            || $this->hasOwnAccess($user, $professional, PermissionKey::ProfessionalOwnAvailabilityManage);
    }

    /**
     * Gestão de ausências e bloqueios. Bloqueios de escopo "todas as
     * unidades" (`$unit = null`) só podem ser geridos por quem tem acesso
     * amplo — um Gerente de unidade nunca pode bloquear o profissional em
     * todas as unidades de uma vez, só na(s) unidade(s) que gerencia.
     */
    public function manageTimeBlocks(User $user, Professional $professional, ?Unit $unit): bool
    {
        if ($this->hasBroadProfessionalAccess($user, $professional->organization_id)) {
            return true;
        }

        if ($this->hasOwnAccess($user, $professional, PermissionKey::ProfessionalOwnTimeBlocksManage)) {
            return true;
        }

        if ($unit === null) {
            return false;
        }

        return $this->hasUnitScopedAccess($user, $professional->organization_id, $unit, PermissionKey::ProfessionalTimeBlocksManage);
    }

    private function hasBroadProfessionalAccess(User $user, string $organizationId): bool
    {
        return $this->hasActiveMembership($user, $organizationId, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $organizationId);
    }

    /**
     * Autoatendimento: o profissional gerencia somente o próprio cadastro,
     * nunca o de outro. O vínculo `Professional.user_id` sozinho nunca
     * basta — exige também membership ativo na mesma organização, e-mail
     * verificado, usuário ativo e a permissão granular correspondente (o
     * papel "Profissional" a recebe por padrão, mas ela pode ser revogada
     * como qualquer outra permissão do catálogo — o vínculo não é um bypass
     * de autorização).
     */
    private function hasOwnAccess(User $user, Professional $professional, PermissionKey $permission): bool
    {
        if ($professional->user_id === null || $professional->user_id !== $user->id) {
            return false;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $professional->organization_id)) {
            return false;
        }

        return $this->permissionChecker->can($user, $permission, $professional->organization_id);
    }

    private function hasUnitScopedAccess(User $user, string $organizationId, Unit $unit, PermissionKey $permission): bool
    {
        if (! $this->permissionChecker->can($user, $permission, $organizationId)) {
            return false;
        }

        return $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active)
            ->whereHas('unitMemberships', function ($query) use ($unit) {
                $query->where('unit_id', $unit->id)
                    ->where('is_manager', true)
                    ->where('status', RecordStatus::Active);
            })
            ->exists();
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
