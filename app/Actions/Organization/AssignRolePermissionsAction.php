<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Auditing\AuditLogger;

class AssignRolePermissionsAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  array<int, string>  $permissionKeys */
    public function handle(Role $role, array $permissionKeys): Role
    {
        $before = $role->permissions()->pluck('key')->all();

        $validKeys = array_map(fn (PermissionKey $key) => $key->value, PermissionKey::cases());
        $permissionIds = Permission::query()
            ->whereIn('key', array_intersect($permissionKeys, $validKeys))
            ->pluck('id');

        $role->permissions()->sync($permissionIds);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $role,
            before: ['permissions' => $before],
            after: ['permissions' => $role->permissions()->pluck('key')->all()],
            organization: $role->organization,
        );

        return $role;
    }
}
