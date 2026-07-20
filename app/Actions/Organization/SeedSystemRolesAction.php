<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;

/**
 * Garante que uma organização tenha os 7 papéis de sistema (ver
 * App\Enums\SystemRole) com suas permissões padrão. Idempotente: chamado
 * tanto no onboarding de uma organização nova quanto por seeders — nunca
 * sobrescreve as permissões de um papel que já existia (evita apagar
 * personalizações feitas pelo administrador ao rodar de novo).
 */
class SeedSystemRolesAction
{
    public function handle(Organization $organization): void
    {
        $permissionIdsByKey = $this->ensurePermissionCatalog();

        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => $systemRole->value],
                [
                    'name' => $systemRole->label(),
                    'description' => $systemRole->description(),
                    'is_system' => true,
                ],
            );

            if (! $role->wasRecentlyCreated) {
                continue;
            }

            $permissionIds = array_map(
                fn (PermissionKey $key): string => $permissionIdsByKey[$key->value],
                $systemRole->defaultPermissions(),
            );

            $role->permissions()->sync($permissionIds);
        }
    }

    /** @return array<string, string> Chave da permissão => id do registro. */
    private function ensurePermissionCatalog(): array
    {
        foreach (PermissionKey::cases() as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key->value],
                ['group' => $key->group(), 'label' => $key->label()],
            );
        }

        /** @var array<string, string> */
        return Permission::query()->pluck('id', 'key')->all();
    }
}
