<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

function seedSystemRoles(Organization $organization): void
{
    app(SeedSystemRolesAction::class)->handle($organization);
}

function nonOwnerActingWithRole(Organization $organization, SystemRole $systemRole): array
{
    seedSystemRoles($organization);

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', $systemRole->value)->firstOrFail();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create(['role_id' => $role->id]);

    session(['active_organization_id' => $organization->id]);

    return compact('user', 'membership', 'role');
}

it('lets the owner list roles including the auto-seeded system roles', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);

    $this->actingAs($ctx['user'])
        ->get(route('settings.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/roles/Index')
            ->has('roles', 7)
        );
});

it('lets the owner create a custom role with a chosen subset of permissions', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);

    $this->actingAs($ctx['user'])
        ->post(route('settings.roles.store'), [
            'name' => 'Estagiário',
            'description' => 'Acesso limitado para triagem.',
            'permissions' => [PermissionKey::DashboardView->value, PermissionKey::UnitsView->value],
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('name', 'Estagiário')->first();

    expect($role)->not->toBeNull()
        ->and($role->is_system)->toBeFalse()
        ->and($role->permissions()->pluck('key')->sort()->values()->all())->toBe([
            PermissionKey::DashboardView->value,
            PermissionKey::UnitsView->value,
        ]);
});

it('blocks deleting a system role', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $this->actingAs($ctx['user'])
        ->delete(route('settings.roles.destroy', $role))
        ->assertForbidden();

    expect(Role::query()->find($role->id))->not->toBeNull();
});

it('blocks reassigning permissions on the Proprietário role', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);

    $ownerRole = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Owner->value)->firstOrFail();

    $this->actingAs($ctx['user'])
        ->put(route('settings.roles.permissions', $ownerRole), [
            'permissions' => [PermissionKey::DashboardView->value],
        ])
        ->assertForbidden();
});

it('lets the owner delete a custom role and preserves the membership without a role', function () {
    $ctx = ownerActingInOrganization();

    $role = Role::factory()->for($ctx['organization'])->create();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);

    $this->actingAs($ctx['user'])
        ->delete(route('settings.roles.destroy', $role))
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    expect(Role::query()->find($role->id))->toBeNull()
        ->and($membership->fresh()->role_id)->toBeNull();
});

it('duplicates a role preserving its permission set as a new custom role', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);

    $source = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Auditor->value)->firstOrFail();

    $this->actingAs($ctx['user'])
        ->post(route('settings.roles.duplicate', $source))
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $copy = Role::query()->where('organization_id', $ctx['organization']->id)->where('name', $source->name.' (cópia)')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->is_system)->toBeFalse()
        ->and($copy->permissions()->pluck('key')->sort()->values()->all())
        ->toBe($source->permissions()->pluck('key')->sort()->values()->all());
});

it('grants a non-owner unit update access through a role permission, without making them an owner', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $ctx = nonOwnerActingWithRole($organization, SystemRole::UnitManager);
    UnitMembership::factory()->for($ctx['membership'], 'organizationMembership')->for($unit, 'unit')->create();
    session(['active_unit_id' => $unit->id]);

    expect($ctx['membership']->is_owner)->toBeFalse();

    $this->actingAs($ctx['user'])
        ->put(route('settings.units.update', $unit), [
            'name' => 'Unidade Renomeada',
            'phone' => null,
            'whatsapp' => null,
            'email' => null,
            'timezone' => 'America/Sao_Paulo',
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    expect($unit->fresh()->name)->toBe('Unidade Renomeada');
});

it('still blocks a non-owner without the matching permission from updating a unit', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $ctx = nonOwnerActingWithRole($organization, SystemRole::Reception);
    UnitMembership::factory()->for($ctx['membership'], 'organizationMembership')->for($unit, 'unit')->create();
    session(['active_unit_id' => $unit->id]);

    $this->actingAs($ctx['user'])
        ->put(route('settings.units.update', $unit), [
            'name' => 'Não deveria funcionar',
            'timezone' => 'America/Sao_Paulo',
        ])
        ->assertForbidden();
});

it('lets the platform admin manage roles even without ownership of the organization', function () {
    $organization = Organization::factory()->create();
    seedSystemRoles($organization);
    $admin = User::factory()->create(['is_platform_admin' => true]);
    OrganizationMembership::factory()->for($organization)->for($admin)->create();
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($admin)
        ->post(route('settings.roles.store'), [
            'name' => 'Papel via admin técnico',
            'permissions' => [PermissionKey::DashboardView->value],
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    expect(Role::query()->where('organization_id', $organization->id)->where('name', 'Papel via admin técnico')->exists())->toBeTrue();
});
