<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;

it('lets the owner view appointment requests scoped to their organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));

    AppointmentRequest::factory()->for($organization)->create(['name' => 'Da minha clínica']);
    AppointmentRequest::factory()->for(Organization::factory()->create())->create(['name' => 'De outra clínica']);

    $this->actingAs($user)->get('/settings/site/appointment-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/site/appointment-requests/Index')
            ->where('requests.0.name', 'Da minha clínica')
            ->has('requests', 1));
});

it('lets the owner update the status of a request', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/status", [
        'status' => 'contacted',
    ])->assertRedirect();

    expect($request->fresh()->status->value)->toBe('contacted');
});

it('blocks a non-owner without site.appointments.view from listing requests', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->get('/settings/site/appointment-requests')->assertForbidden();
});

it('grants the reception role access to manage appointment requests', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    seedSystemRoles($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create(['role_id' => $role->id]);
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($member)->get('/settings/site/appointment-requests')->assertOk();
    $this->actingAs($member)->patch("/settings/site/appointment-requests/{$request->id}/status", [
        'status' => 'scheduled',
    ])->assertRedirect();
});
