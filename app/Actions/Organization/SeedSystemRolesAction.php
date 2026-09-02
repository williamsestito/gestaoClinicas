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
 * tanto no onboarding de uma organização nova quanto por seeders.
 *
 * Papel recém-criado: recebe o conjunto completo de `defaultPermissions()`.
 * Papel já existente: nunca perde uma permissão que já tinha (preserva
 * qualquer customização feita pelo administrador), mas GANHA
 * aditivamente qualquer permissão nova que passou a fazer parte do
 * conjunto padrão desde a última vez que essa organização foi
 * sincronizada — sem isso, toda organização criada antes de uma nova
 * `PermissionKey` ser adicionada ao catálogo de um papel de sistema fica
 * presa para sempre no conjunto antigo (ex.: organizações criadas antes
 * de `patients.view-own`/`appointments.view-own`/`professional-
 * availability.view-own` existirem nunca as receberiam sem esta sync).
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

            $defaultPermissionIds = array_map(
                fn (PermissionKey $key): string => $permissionIdsByKey[$key->value],
                $systemRole->defaultPermissions(),
            );

            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync($defaultPermissionIds);

                continue;
            }

            $role->permissions()->syncWithoutDetaching($defaultPermissionIds);
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
