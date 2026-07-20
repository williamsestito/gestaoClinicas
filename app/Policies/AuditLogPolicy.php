<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Logs de auditoria são sempre somente leitura — nunca editáveis ou
 * excluíveis. Chamado de duas formas: pelo Filament, sem organização
 * (`Gate::authorize('viewAny', AuditLog::class)` — restrito a
 * is_platform_admin), e pela tela "Auditoria" do sistema operacional,
 * com a organização ativa (proprietário, administrador técnico, ou papel
 * com a permissão `audit.view`).
 */
class AuditLogPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        $isOwner = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->where('is_owner', true)
            ->exists();

        return $isOwner || $this->permissionChecker->can($user, PermissionKey::AuditView, $organization->id);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->is_platform_admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
