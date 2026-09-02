<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

it('shares a formatted tenant context via inertia, never the raw legal entity document', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create(['is_manager' => true]);

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('tenant.organization.id', $organization->id)
        ->where('tenant.unit.id', $headquarters->id)
        ->where('tenant.isOwner', true)
        ->where('tenant.isUnitManager', true)
        ->missing('tenant.organization.document')
        ->missing('tenant.legalEntity'));
});

it('shares the member role name, never a generic "Membro" placeholder', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $role = Role::factory()->for($organization)->create(['name' => 'Profissional']);

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('tenant.isOwner', false)
        ->where('tenant.membership.role_name', 'Profissional'));
});

it('never shows a role name for the owner, who has full access regardless of any assigned role', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('tenant.isOwner', true)
        ->where('tenant.membership.role_name', null));
});

it('excludes inactive and suspended organizations from the organization selector', function () {
    $user = User::factory()->create();

    $activeOrganization = Organization::factory()->create();
    $suspendedOrganization = Organization::factory()->suspended()->create();

    OrganizationMembership::factory()->owner()->for($activeOrganization)->for($user)->create();
    OrganizationMembership::factory()->owner()->for($suspendedOrganization)->for($user)->create();

    $response = $this->actingAs($user)->get('/context/organization');

    $response->assertInertia(fn ($page) => $page
        ->has('organizations', 1)
        ->where('organizations.0.id', $activeOrganization->id));
});

it('excludes inactive units from the unit selector', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $activeUnit = Unit::factory()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $inactiveUnit = Unit::factory()->for($organization)->for($legalEntity, 'legalEntity')->create(['status' => RecordStatus::Inactive]);

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($activeUnit, 'unit')->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($inactiveUnit, 'unit')->create();

    session(['active_organization_id' => $organization->id]);

    $response = $this->actingAs($user)->get('/context/unit');

    $response->assertInertia(fn ($page) => $page
        ->has('units', 1)
        ->where('units.0.id', $activeUnit->id));
});
