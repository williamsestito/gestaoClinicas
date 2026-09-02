<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Role;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclui um papel personalizado. Papéis de sistema nunca chegam aqui (já
 * bloqueados pela RolePolicy); vínculos que ainda usam o papel excluído
 * ficam com `role_id = null` (nullOnDelete na migration) — o usuário não
 * perde a organização, só perde a atribuição de papel granular.
 */
class DeleteRoleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Papéis de sistema não podem ser excluídos.',
            ]);
        }

        $organization = $role->organization;
        $name = $role->name;

        $role->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $role,
            before: ['name' => $name],
            organization: $organization,
        );
    }
}
