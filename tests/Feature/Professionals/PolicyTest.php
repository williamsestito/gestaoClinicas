<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\Role;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;

/** Cria um membro ativo (não proprietário) com um papel que tem apenas a permissão informada. */
function memberWithPermission(Organization $organization, PermissionKey $permissionKey): User
{
    $user = User::factory()->create();
    $permission = Permission::query()->firstOrCreate(
        ['key' => $permissionKey->value],
        ['group' => $permissionKey->group(), 'label' => $permissionKey->label()],
    );
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);

    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'is_owner' => false,
        'role_id' => $role->id,
    ]);

    return $user;
}

function plainMember(Organization $organization): User
{
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'is_owner' => false,
    ]);

    return $user;
}

function ownerOf(Organization $organization): User
{
    $user = User::factory()->create();
    OrganizationMembership::factory()->owner()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
    ]);

    return $user;
}

it('lets any active member view specialties/services/professionals/registrations, but not manage them', function () {
    $organization = Organization::factory()->create();
    $member = plainMember($organization);
    $specialty = Specialty::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id]);

    expect($member->can('view', $specialty))->toBeTrue()
        ->and($member->can('update', $specialty))->toBeFalse()
        ->and($member->can('view', $service))->toBeTrue()
        ->and($member->can('update', $service))->toBeFalse()
        ->and($member->can('view', $professional))->toBeTrue()
        ->and($member->can('update', $professional))->toBeFalse()
        ->and($member->can('view', $registration))->toBeTrue()
        ->and($member->can('update', $registration))->toBeFalse();
});

it('lets the organization owner manage specialties/services/professionals/registrations', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);
    $specialty = Specialty::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id]);

    expect($owner->can('update', $specialty))->toBeTrue()
        ->and($owner->can('delete', $specialty))->toBeTrue()
        ->and($owner->can('update', $service))->toBeTrue()
        ->and($owner->can('update', $professional))->toBeTrue()
        ->and($owner->can('manageUnits', $professional))->toBeTrue()
        ->and($owner->can('manageServices', $professional))->toBeTrue()
        ->and($owner->can('update', $registration))->toBeTrue();
});

it('lets a member with the SpecialtiesManage permission manage specialties without being owner', function () {
    $organization = Organization::factory()->create();
    $member = memberWithPermission($organization, PermissionKey::SpecialtiesManage);
    $specialty = Specialty::factory()->for($organization)->create();

    expect($member->can('update', $specialty))->toBeTrue();
});

it('lets a member with the ProfessionalsManageUnits permission manage professional-unit links', function () {
    $organization = Organization::factory()->create();
    $member = memberWithPermission($organization, PermissionKey::ProfessionalsManageUnits);
    $professional = Professional::factory()->for($organization)->create();

    expect($member->can('manageUnits', $professional))->toBeTrue()
        ->and($member->can('manageServices', $professional))->toBeFalse();
});

it('blocks a member of another organization from managing or viewing these resources', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $outsider = plainMember($otherOrganization);
    $specialty = Specialty::factory()->for($organization)->create();

    expect($outsider->can('view', $specialty))->toBeFalse()
        ->and($outsider->can('update', $specialty))->toBeFalse();
});

it('grants a platform admin full access regardless of membership', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $specialty = Specialty::factory()->for($organization)->create();

    expect($admin->can('view', $specialty))->toBeTrue()
        ->and($admin->can('update', $specialty))->toBeTrue();
});
