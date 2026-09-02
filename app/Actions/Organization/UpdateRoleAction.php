<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Role;
use App\Support\Auditing\AuditLogger;

class UpdateRoleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Role $role, string $name, ?string $description): Role
    {
        $before = ['name' => $role->name, 'description' => $role->description];

        $role->update(['name' => $name, 'description' => $description]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $role,
            before: $before,
            after: ['name' => $role->name, 'description' => $role->description],
            organization: $role->organization,
        );

        return $role;
    }
}
