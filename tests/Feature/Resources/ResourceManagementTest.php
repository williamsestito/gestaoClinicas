<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Service;
use App\Models\SharedResource;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;

it('shows an empty listing for a brand new clinic', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)
        ->get('/settings/resources')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/resources/Index')
            ->where('resources', []));
});

it('creates a resource for the active organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();

    $this->actingAs($user)->post('/settings/resources', [
        'unit_id' => $unit->id,
        'name' => 'Sala 1',
        'type' => 'Sala',
    ])->assertRedirect('/settings/resources');

    $resource = SharedResource::query()->where('name', 'Sala 1')->firstOrFail();
    expect($resource->unit_id)->toBe($unit->id)
        ->and($resource->status)->toBe(RecordStatus::Active)
        ->and(AuditLog::query()->where('auditable_id', $resource->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('rejects a duplicate name within the same unit but allows it in another unit', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    SharedResource::factory()->for($organization)->for($unit)->create(['name' => 'Sala 1']);

    $this->actingAs($user)->post('/settings/resources', [
        'unit_id' => $unit->id,
        'name' => 'Sala 1',
    ])->assertSessionHasErrors('name');

    $otherUnit = Unit::factory()->for($organization)->create();
    $this->actingAs($user)->post('/settings/resources', [
        'unit_id' => $otherUnit->id,
        'name' => 'Sala 1',
    ])->assertRedirect('/settings/resources');

    expect(SharedResource::query()->where('name', 'Sala 1')->count())->toBe(2);
});

it('updates a resource', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $resource = SharedResource::factory()->for($organization)->for($unit)->create();

    $this->actingAs($user)->put("/settings/resources/{$resource->id}", [
        'unit_id' => $unit->id,
        'name' => 'Novo Nome',
        'type' => 'Equipamento',
    ])->assertRedirect('/settings/resources');

    expect($resource->fresh()->name)->toBe('Novo Nome')
        ->and($resource->fresh()->type)->toBe('Equipamento');
});

it('activates and deactivates a resource', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $resource = SharedResource::factory()->for($organization)->for($unit)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->patch("/settings/resources/{$resource->id}/deactivate")
        ->assertRedirect();
    expect($resource->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/resources/{$resource->id}/activate")
        ->assertRedirect();
    expect($resource->fresh()->status)->toBe(RecordStatus::Active);
});

it('blocks deleting a resource linked to a non-final appointment', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $resource = SharedResource::factory()->for($organization)->for($unit)->create();

    $professional = Professional::factory()->for($organization)->create();
    $patient = Patient::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $appointment = Appointment::factory()->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'status' => AppointmentStatus::Confirmed,
    ]);
    $appointment->resources()->attach($resource->id, ['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/resources/{$resource->id}")
        ->assertSessionHasErrors('resource');

    expect($resource->fresh()->trashed())->toBeFalse();
});

it('logically deletes an unlinked resource and preserves its history', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $resource = SharedResource::factory()->for($organization)->for($unit)->create();

    $this->actingAs($user)->delete("/settings/resources/{$resource->id}")
        ->assertRedirect();

    expect($resource->fresh()->trashed())->toBeTrue()
        ->and(SharedResource::query()->find($resource->id))->toBeNull()
        ->and(SharedResource::withTrashed()->find($resource->id))->not->toBeNull();
});

it('restores a deleted resource as inactive', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $resource = SharedResource::factory()->for($organization)->for($unit)->create(['status' => RecordStatus::Active]);
    $resource->delete();

    $this->actingAs($user)->post("/settings/resources/{$resource->id}/restore")
        ->assertRedirect();

    expect($resource->fresh()->trashed())->toBeFalse()
        ->and($resource->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('blocks a member without the manage permission from creating a resource', function () {
    $organization = Organization::factory()->create();
    $unit = Unit::factory()->for($organization)->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/resources', ['unit_id' => $unit->id, 'name' => 'Sala 1'])
        ->assertForbidden();
});

it('allows a member with the resources.manage permission to create a resource', function () {
    $organization = Organization::factory()->create();
    $unit = Unit::factory()->for($organization)->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ResourcesManage->value,
        'group' => PermissionKey::ResourcesManage->group(),
        'label' => PermissionKey::ResourcesManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/resources', ['unit_id' => $unit->id, 'name' => 'Sala 1'])
        ->assertRedirect('/settings/resources');
});

it('blocks access to a resource belonging to another organization even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $otherUnit = Unit::factory()->for($otherOrganization)->create();
    $foreignResource = SharedResource::factory()->for($otherOrganization)->for($otherUnit)->create();

    $this->actingAs($user)->get("/settings/resources/{$foreignResource->id}/edit")
        ->assertNotFound();

    $this->actingAs($user)->put("/settings/resources/{$foreignResource->id}", [
        'unit_id' => $otherUnit->id,
        'name' => 'Hackeado',
    ])->assertNotFound();
});

it('blocks a real conflicting reservation of the same resource, regardless of professional', function () {
    $setup = appointmentSetup();
    $resource = SharedResource::factory()->for($setup['organization'])->for($setup['unit'])->create();

    $otherProfessional = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    $otherProfessionalUnit = ProfessionalUnit::factory()->for($otherProfessional)->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'status' => RecordStatus::Active,
    ]);
    ProfessionalWorkingHour::factory()->for($otherProfessionalUnit, 'professionalUnit')->create([
        'organization_id' => $setup['organization']->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '18:00',
    ]);
    ProfessionalService::factory()->for($otherProfessional)->create([
        'organization_id' => $setup['organization']->id,
        'service_id' => $setup['service']->id,
    ]);
    $otherPatient = Patient::factory()->for($setup['organization'])->create();

    $startsAt = Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->utc();

    $first = Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed,
    ]);
    $first->resources()->attach($resource->id, ['organization_id' => $setup['organization']->id]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $otherProfessional->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $setup['service']->id,
        'starts_at' => '2026-08-03T09:00:00',
        'resource_ids' => [$resource->id],
    ])->assertSessionHasErrors('resource_ids');

    expect(Appointment::query()->where('professional_id', $otherProfessional->id)->count())->toBe(0);
});
