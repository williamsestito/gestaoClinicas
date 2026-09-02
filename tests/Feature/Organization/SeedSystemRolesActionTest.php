<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;

it('grants the full default permission set to a newly created system role', function () {
    $organization = Organization::factory()->create();

    app(SeedSystemRolesAction::class)->handle($organization);

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $expected = array_map(fn ($key) => $key->value, SystemRole::Professional->defaultPermissions());

    expect($role->permissions()->pluck('key')->sort()->values()->all())->toBe(collect($expected)->sort()->values()->all());
});

it('additively grants a newly added default permission to an already-existing system role, without touching what it already had', function () {
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    // Simula uma organização criada antes de uma nova PermissionKey existir:
    // o papel só tem um subconjunto antigo do que é o padrão hoje.
    $stalePermission = Permission::query()->where('key', 'dashboard.view')->firstOrFail();
    $role->permissions()->sync([$stalePermission->id]);

    // Customização manual do administrador: uma permissão fora do padrão.
    $customPermission = Permission::query()->where('key', 'audit.view')->firstOrFail();
    $role->permissions()->attach($customPermission->id);

    app(SeedSystemRolesAction::class)->handle($organization);

    $keys = $role->fresh()->permissions()->pluck('key')->all();

    expect($keys)->toContain('dashboard.view', 'audit.view', 'patients.view-own', 'appointments.view-own');
});

it('never revokes a permission a system role already had, even one no longer in the default set', function () {
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $unitsView = Permission::query()->where('key', 'units.view')->firstOrFail();
    $role->permissions()->attach($unitsView->id);

    app(SeedSystemRolesAction::class)->handle($organization);

    expect($role->fresh()->permissions()->pluck('key')->all())->toContain('units.view');
});
