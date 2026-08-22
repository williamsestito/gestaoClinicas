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
 * Visualização liberada para qualquer membro ativo da organização — com
 * uma exceção deliberada: um usuário que é ele mesmo um profissional
 * vinculado (`Professional.user_id`) só enxerga a própria ficha por essa
 * via; para ver a de um colega, precisa da permissão granular
 * (`ProfessionalsView`/`ProfessionalsManage`) ou ser proprietário — nunca
 * o simples "membro ativo" (ver `isLinkedProfessional()`). Sem essa
 * exceção, qualquer profissional autoatendido enxergaria unidades,
 * jornada e ausências de qualquer colega, o que nunca foi a intenção da
 * regra original (pensada para recepção/administração precisarem
 * consultar o cadastro de qualquer profissional, não para o próprio
 * profissional). Gestão do cadastro exige o proprietário ou a permissão
 * granular correspondente via papel atribuído. Gerir vínculos com
 * unidades/serviços tem permissões próprias
 * (`ProfessionalsManageUnits`/`ProfessionalsManageServices`) — vincular um
 * profissional a um `User` nunca é autorizado por esta policy nem concede
 * permissões: isso depende exclusivamente de OrganizationMembership/Role
 * (ver App\Support\Authorization\PermissionChecker).
 *
 * Autoatendimento — dados próprios: `update()` (dados gerais: nome,
 * contato, CPF, data de nascimento, biografia) e `manageSpecialties()`
 * também aceitam o próprio profissional vinculado, SEM exigir nenhuma
 * permissão granular extra — corrigir/completar o próprio cadastro básico
 * é esperado de qualquer profissional autoatendido, não uma permissão
 * administrativa (ver `isSelfProfessional()`). Deliberadamente NÃO
 * estendido a `manageUnits()`/`manageServices()`: em quais unidades atua e
 * quais serviços executa continuam decisões operacionais da clínica, não
 * do próprio profissional. `manageAvailability()`/`manageTimeBlocks()`
 * seguem exigindo a permissão granular correspondente
 * (`ProfessionalOwnAvailabilityManage`/`ProfessionalOwnTimeBlocksManage`)
 * — ver `hasOwnAccess()` — porque afetam a agenda pública, não só o
 * próprio cadastro.
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

        if ($this->isLinkedProfessional($user, $organization->id)) {
            return $this->hasBroadProfessionalAccess($user, $organization->id)
                || $this->permissionChecker->can($user, PermissionKey::ProfessionalsView, $organization->id);
        }

        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, Professional $professional): bool
    {
        if ($this->isSelfProfessional($user, $professional)) {
            return true;
        }

        if ($this->isLinkedProfessional($user, $professional->organization_id)) {
            return $this->hasBroadProfessionalAccess($user, $professional->organization_id)
                || $this->permissionChecker->can($user, PermissionKey::ProfessionalsView, $professional->organization_id);
        }

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
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManage, $professional->organization_id)
            || $this->isSelfProfessional($user, $professional);
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
            || $this->permissionChecker->can($user, PermissionKey::ProfessionalsManageSpecialties, $professional->organization_id)
            || $this->isSelfProfessional($user, $professional);
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
     * Identidade: é o próprio profissional, com vínculo ativo, e-mail
     * verificado e usuário ativo. Sozinho, já basta para `view()` — ver a
     * própria ficha nunca deveria depender de uma permissão extra — mas
     * NÃO basta para gerir nada (jornada, ausências, unidades...), que
     * sempre revalida a permissão granular correspondente em
     * `hasOwnAccess()`.
     */
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

    /**
     * Autoatendimento: o profissional gerencia somente o próprio cadastro,
     * nunca o de outro. O vínculo `Professional.user_id` sozinho nunca
     * basta — exige também a permissão granular correspondente (o papel
     * "Profissional" a recebe por padrão, mas ela pode ser revogada como
     * qualquer outra permissão do catálogo — o vínculo não é um bypass de
     * autorização).
     */
    private function hasOwnAccess(User $user, Professional $professional, PermissionKey $permission): bool
    {
        if (! $this->isSelfProfessional($user, $professional)) {
            return false;
        }

        return $this->permissionChecker->can($user, $permission, $professional->organization_id);
    }

    /**
     * É o usuário mesmo um profissional vinculado nesta organização
     * (qualquer um, não necessariamente o `$professional` sendo
     * verificado)? Define se `view()`/`viewAny()` aplicam a exceção de
     * autoatendimento (só a própria ficha) em vez do acesso amplo padrão
     * de "qualquer membro ativo".
     */
    private function isLinkedProfessional(User $user, string $organizationId): bool
    {
        return Professional::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->exists();
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
