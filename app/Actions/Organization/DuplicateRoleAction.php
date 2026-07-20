<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\Role;

/**
 * Duplica um papel existente (de sistema ou personalizado) como um novo
 * papel personalizado, copiando as permissões atuais. O papel original
 * nunca é alterado.
 */
class DuplicateRoleAction
{
    public function __construct(private readonly CreateRoleAction $createRole) {}

    public function handle(Role $role): Role
    {
        $permissionKeys = $role->permissions()->pluck('key')->all();

        return $this->createRole->handle(
            organization: $role->organization,
            name: $role->name.' (cópia)',
            description: $role->description,
            permissionKeys: $permissionKeys,
        );
    }
}
