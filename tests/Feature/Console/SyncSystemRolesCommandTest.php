<?php

declare(strict_types=1);

use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;

it('adds a new default permission to an existing organization\'s system role without removing custom grants', function () {
    $organization = Organization::factory()->create();
    $role = Role::query()->create([
        'organization_id' => $organization->id,
        'slug' => SystemRole::Professional->value,
        'name' => SystemRole::Professional->label(),
        'description' => SystemRole::Professional->description(),
        'is_system' => true,
    ]);

    $customPermission = Permission::query()->firstOrCreate(
        ['key' => 'settings.view'],
        ['group' => 'Configurações', 'label' => 'Ver configurações'],
    );
    $role->permissions()->attach($customPermission->id);

    $this->artisan('app:sync-system-roles')->assertExitCode(0);

    $role->refresh();
    $keys = $role->permissions()->pluck('key')->all();

    expect($keys)->toContain(PermissionKey::MedicalRecordsManageOwn->value)
        ->and($keys)->toContain('settings.view');
});

it('never grants the two clinical permissions to the organization owner role (RN-015/RN-016)', function () {
    $organization = Organization::factory()->create();

    $this->artisan('app:sync-system-roles')->assertExitCode(0);

    $ownerRole = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Owner->value)->firstOrFail();
    $keys = $ownerRole->permissions()->pluck('key')->all();

    expect($keys)->not->toContain(PermissionKey::MedicalRecordsManage->value)
        ->and($keys)->not->toContain(PermissionKey::MedicalRecordsManageOwn->value);
});
