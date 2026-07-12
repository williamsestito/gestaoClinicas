<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Logs de auditoria são somente leitura e restritos a administradores da
 * plataforma (acessados via Filament) — nunca editáveis ou excluíveis.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
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
