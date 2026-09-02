<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
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

it('groups pending appointment requests by professional for the admin/reception dashboard alert', function () {
    $ctx = ownerActingInOrganization();
    $professionalA = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    $professionalB = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dr João Paiva']);

    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professionalA->id, 'name' => 'Lead 1']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professionalA->id, 'name' => 'Lead 2']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professionalB->id, 'name' => 'Lead 3']);
    AppointmentRequest::factory()->contacted()->for($ctx['organization'])->create(['professional_id' => $professionalA->id, 'name' => 'Já contatado']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['status' => AppointmentRequestStatus::Cancelled, 'professional_id' => $professionalA->id, 'name' => 'Cancelado']);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendingAppointmentRequestsByProfessional', 2)
            ->where('pendingAppointmentRequestsByProfessional.0.professional_name', 'Dra Juliana Cruz')
            ->where('pendingAppointmentRequestsByProfessional.0.count', 2)
            ->where('pendingAppointmentRequestsByProfessional.1.professional_name', 'Dr João Paiva')
            ->where('pendingAppointmentRequestsByProfessional.1.count', 1));
});

it('never exposes the pending-appointment-request alert to a role without site.appointments.view', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    AppointmentRequest::factory()->for($ctx['organization'])->create();

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Finance->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingAppointmentRequestsByProfessional', null));
});

it('shows the pending-appointment-request alert to a reception user, for professionals other than themselves', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    $professional = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professional->id]);

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Reception->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendingAppointmentRequestsByProfessional', 1)
            ->where('pendingAppointmentRequestsByProfessional.0.professional_name', 'Dra Juliana Cruz'));
});

it('shows today\'s organization-wide agenda, filterable by professional, for admin/reception', function () {
    $ctx = ownerActingInOrganization();
    $professionalA = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    $professionalB = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dr João Paiva']);
    Appointment::factory()->for($ctx['organization'])->for($professionalA)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->setTime(9, 0)]);
    Appointment::factory()->for($ctx['organization'])->for($professionalB)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->setTime(10, 0)]);
    Appointment::factory()->for($ctx['organization'])->for($professionalA)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->addDay()->setTime(9, 0)]);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orgAgenda.appointments', 2)
            ->has('orgAgenda.professionals', 2));

    $this->actingAs($ctx['user'])
        ->get(route('dashboard', ['agenda_professional_id' => $professionalA->id]))
        ->assertInertia(fn ($page) => $page
            ->has('orgAgenda.appointments', 1)
            ->where('orgAgenda.appointments.0.professional_name', 'Dra Juliana Cruz'));
});

it('never exposes the organization agenda to a role without appointments.view', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    Appointment::factory()->for($ctx['organization'])->create(['status' => AppointmentStatus::Confirmed]);

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Finance->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('orgAgenda', null));
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
