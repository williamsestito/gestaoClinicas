<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Str;

/**
 * Cria um papel personalizado (nunca de sistema) para a organização, com
 * um conjunto inicial de permissões.
 */
class CreateRoleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  array<int, string>  $permissionKeys */
    public function handle(Organization $organization, string $name, ?string $description, array $permissionKeys): Role
    {
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($organization, $name),
            'description' => $description,
            'is_system' => false,
        ]);

        $validKeys = array_map(fn (PermissionKey $key) => $key->value, PermissionKey::cases());
        $permissionIds = Permission::query()
            ->whereIn('key', array_intersect($permissionKeys, $validKeys))
            ->pluck('id');

        $role->permissions()->sync($permissionIds);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $role,
            after: ['name' => $role->name, 'permissions' => $permissionKeys],
            organization: $organization,
        );

        return $role;
    }

    private function uniqueSlug(Organization $organization, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Role::query()->where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
