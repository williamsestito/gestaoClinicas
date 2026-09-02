<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;

it('has a label and a group for every new professionals-domain permission key', function () {
    $newKeys = [
        PermissionKey::SpecialtiesView,
        PermissionKey::SpecialtiesManage,
        PermissionKey::ServicesView,
        PermissionKey::ServicesManage,
        PermissionKey::ProfessionalsView,
        PermissionKey::ProfessionalsManage,
        PermissionKey::ProfessionalsManageUnits,
        PermissionKey::ProfessionalsManageServices,
        PermissionKey::ProfessionalRegistrationsView,
        PermissionKey::ProfessionalRegistrationsManage,
    ];

    foreach ($newKeys as $key) {
        expect($key->label())->toBeString()->not->toBe('')
            ->and($key->group())->toBeString()->not->toBe('')
            ->and($key->group())->not->toBe('Geral');
    }
});

it('grants the clinic admin system role every new professionals-domain permission', function () {
    $keys = array_map(fn (PermissionKey $key) => $key->value, SystemRole::ClinicAdmin->defaultPermissions());

    expect($keys)->toContain('specialties.manage', 'services.manage', 'professionals.manage', 'professional-registrations.manage');
});

it('seeds the permission catalog and system roles without error after the new keys were added', function () {
    $organization = Organization::factory()->create();

    app(SeedSystemRolesAction::class)->handle($organization);

    expect(Permission::query()->where('key', PermissionKey::ProfessionalsManage->value)->exists())->toBeTrue();

    $clinicAdminRole = Role::query()
        ->where('organization_id', $organization->id)
        ->where('slug', SystemRole::ClinicAdmin->value)
        ->firstOrFail();

    expect($clinicAdminRole->permissions()->where('key', PermissionKey::ProfessionalsManage->value)->exists())->toBeTrue();
});
