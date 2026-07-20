<?php

declare(strict_types=1);

use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

it('shows real user, unit and legal entity counts on the dashboard', function () {
    $ctx = ownerActingInOrganization();

    $inactiveMember = User::factory()->create(['is_active' => false]);
    OrganizationMembership::factory()->for($ctx['organization'])->for($inactiveMember)->create();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('unitsCount', 1)
            ->where('legalEntitiesCount', 1)
            ->where('usersCount', 2)
            ->where('activeUsersCount', 1)
            ->where('inactiveUsersCount', 1)
        );
});

it('lists pending setup items when domain and SEO are not configured', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('domainConfigured', false)
            ->where('seoConfigured', false)
            ->where('pendingSetupItems', fn ($items) => count($items) > 0)
        );
});

it('reports the domain and SEO as configured once SiteSetting has the data', function () {
    $ctx = ownerActingInOrganization();
    SiteSetting::factory()->create([
        'official_domain' => 'clinicaexemplo.com.br',
        'meta_title' => 'Clínica Exemplo',
        'meta_description' => 'Cuidando de você.',
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('domainConfigured', true)
            ->where('seoConfigured', true)
        );
});

it('shares only the permissions granted by the assigned role in the tenant prop', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    seedSystemRoles($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('tenant.permissions', fn ($permissions) => collect($permissions)->contains(PermissionKey::UnitsView->value)
                && ! collect($permissions)->contains(PermissionKey::UsersInvite->value))
        );
});
