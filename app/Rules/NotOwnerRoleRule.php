<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Impede que o papel "Proprietário" seja atribuído a um vínculo via
 * `role_id` (convite ou edição de usuário). O acesso total do proprietário
 * vem exclusivamente de `organization_membership.is_owner` — reatribuir o
 * papel de sistema daria a mesma cobertura de permissões
 * (`SystemRole::Owner->defaultPermissions()` inclui todas) sem passar pela
 * proteção do último proprietário (App\Support\Tenancy\OwnershipGuard),
 * abrindo uma escalada de privilégio para qualquer usuário autorizado a
 * atribuir papéis.
 */
final readonly class NotOwnerRoleRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $role = Role::query()->whereKey($value)->first();

        if ($role?->isOwnerRole()) {
            $fail('O papel de Proprietário não pode ser atribuído desta forma — ele é definido apenas na criação da organização.');
        }
    }
}
