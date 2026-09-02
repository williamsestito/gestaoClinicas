<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\ModuleKey;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationModule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

it('shows the 4 toggleable modules disabled by default for a brand new clinic', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)
        ->get('/settings/modules')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/modules/Index')
            ->has('modules', 4)
            ->where('modules.0.enabled', false));
});

it('enables a module and logs an audit entry', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $this->actingAs($user)->put('/settings/modules', [
        'modules' => ['dental' => true],
    ])->assertRedirect();

    $module = OrganizationModule::query()
        ->where('organization_id', $organization->id)
        ->where('module_key', ModuleKey::Dental)
        ->firstOrFail();

    expect($module->is_enabled)->toBeTrue()
        ->and($module->enabled_at)->not->toBeNull()
        ->and($organization->fresh()->hasModule(ModuleKey::Dental))->toBeTrue()
        ->and(AuditLog::query()->where('auditable_id', $module->id)->where('action', AuditAction::Activated)->exists())->toBeTrue();
});

it('disables a previously enabled module', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    OrganizationModule::factory()->enabled()->for($organization)->create(['module_key' => ModuleKey::Aesthetics]);

    $this->actingAs($user)->put('/settings/modules', [
        'modules' => ['aesthetics' => false],
    ])->assertRedirect();

    $module = OrganizationModule::query()
        ->where('organization_id', $organization->id)
        ->where('module_key', ModuleKey::Aesthetics)
        ->firstOrFail();

    expect($module->is_enabled)->toBeFalse()
        ->and($module->disabled_at)->not->toBeNull()
        ->and($organization->fresh()->hasModule(ModuleKey::Aesthetics))->toBeFalse();
});

it('always considers the core module enabled without a database row', function () {
    $organization = Organization::factory()->create();

    expect($organization->hasModule(ModuleKey::Core))->toBeTrue()
        ->and(OrganizationModule::query()->where('organization_id', $organization->id)->exists())->toBeFalse();
});

it('ignores unknown module keys submitted in the payload', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $this->actingAs($user)->put('/settings/modules', [
        'modules' => ['dental' => true, 'unknown_module' => true],
    ])->assertRedirect();

    expect(OrganizationModule::query()->where('organization_id', $organization->id)->count())->toBe(4)
        ->and(OrganizationModule::query()->where('organization_id', $organization->id)->where('module_key', 'unknown_module')->exists())->toBeFalse();
});

it('blocks a member without the modules.manage permission from updating modules', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->put('/settings/modules', [
        'modules' => ['dental' => true],
    ])->assertForbidden();
});

it('allows a member with the modules.manage permission to update modules', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ModulesManage->value,
        'group' => PermissionKey::ModulesManage->group(),
        'label' => PermissionKey::ModulesManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->put('/settings/modules', [
        'modules' => ['dental' => true],
    ])->assertRedirect();
});

it('blocks a member with only modules.view from updating modules', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ModulesView->value,
        'group' => PermissionKey::ModulesView->group(),
        'label' => PermissionKey::ModulesView->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get('/settings/modules')
        ->assertOk();

    $this->actingAs($user)->put('/settings/modules', [
        'modules' => ['dental' => true],
    ])->assertForbidden();
});

it('always authorizes the platform admin regardless of membership', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => true]);

    expect($admin->can('view', [OrganizationModule::class, $organization]))->toBeTrue()
        ->and($admin->can('manage', [OrganizationModule::class, $organization]))->toBeTrue();
});

it('blocks access to modules of another organization even with an active session for the first one', function () {
    $userA = actingOwnerWithActiveContext();
    $organizationA = $userA->organizationMemberships()->first()->organization;

    $userB = actingOwnerWithActiveContext();
    $organizationB = $userB->organizationMemberships()->first()->organization;

    OrganizationModule::factory()->enabled()->for($organizationB)->create(['module_key' => ModuleKey::Medical]);

    $this->actingAs($userA)
        ->get('/settings/modules')
        ->assertInertia(fn ($page) => $page
            ->where('modules.2.key', 'medical')
            ->where('modules.2.enabled', false));

    expect($organizationA->fresh()->hasModule(ModuleKey::Medical))->toBeFalse();
});
