<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function storeAppointmentFromRequest(array $setup, AppointmentRequest $sourceRequest): TestResponse
{
    return test()->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'appointment_request_id' => $sourceRequest->id,
    ]);
}

it('converts a pending lead into a real appointment, marking it scheduled and linked', function () {
    $setup = appointmentSetup();
    $lead = AppointmentRequest::factory()->create(['organization_id' => $setup['organization']->id]);

    storeAppointmentFromRequest($setup, $lead)->assertRedirect('/settings/appointments');

    $lead->refresh();
    expect($lead->status)->toBe(AppointmentRequestStatus::Scheduled)
        ->and($lead->appointment_id)->not->toBeNull();

    $appointment = $lead->appointment;
    expect($appointment)->not->toBeNull()
        ->and($appointment->patient_id)->toBe($setup['patient']->id);
});

it('does not convert the same lead twice', function () {
    $setup = appointmentSetup();
    $lead = AppointmentRequest::factory()->create(['organization_id' => $setup['organization']->id]);

    storeAppointmentFromRequest($setup, $lead)->assertRedirect('/settings/appointments');

    // Segunda tentativa, para um horário diferente (o profissional já está
    // ocupado às 9h) — deve falhar por causa do lead já convertido, não por
    // conflito de agenda.
    test()->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T10:00:00',
        'appointment_request_id' => $lead->id,
    ])->assertSessionHasErrors('appointment_request_id');
});

it('does not convert a lead belonging to another organization', function () {
    $setup = appointmentSetup();
    $otherOrganization = Organization::factory()->create();
    $lead = AppointmentRequest::factory()->create(['organization_id' => $otherOrganization->id]);

    storeAppointmentFromRequest($setup, $lead)->assertSessionHasErrors('appointment_request_id');

    expect($lead->fresh()->status)->toBe(AppointmentRequestStatus::Pending);
});

it('does not convert a cancelled lead', function () {
    $setup = appointmentSetup();
    $lead = AppointmentRequest::factory()->create([
        'organization_id' => $setup['organization']->id,
        'status' => AppointmentRequestStatus::Cancelled,
    ]);

    storeAppointmentFromRequest($setup, $lead)->assertSessionHasErrors('appointment_request_id');
});

it('lets a reception user (not the professional) confirm a pending lead for any professional in the organization', function () {
    $setup = appointmentSetup();
    $lead = AppointmentRequest::factory()->create(['organization_id' => $setup['organization']->id]);

    seedSystemRoles($setup['organization']);
    $role = Role::query()->where('organization_id', $setup['organization']->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $receptionUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    OrganizationMembership::factory()->for($setup['organization'])->for($receptionUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);

    test()->actingAs($receptionUser)->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'appointment_request_id' => $lead->id,
    ])->assertRedirect('/settings/appointments');

    $lead->refresh();
    expect($lead->status)->toBe(AppointmentRequestStatus::Scheduled)
        ->and($lead->appointment_id)->not->toBeNull();
});

it('requires appointments.manage to convert a lead, same as any other appointment creation', function () {
    $setup = appointmentSetup();
    $lead = AppointmentRequest::factory()->create(['organization_id' => $setup['organization']->id]);

    $staffUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $permission = Permission::query()->firstOrCreate(
        ['key' => PermissionKey::AppointmentsViewOwn->value],
        ['group' => PermissionKey::AppointmentsViewOwn->group(), 'label' => PermissionKey::AppointmentsViewOwn->label()],
    );
    $role = Role::factory()->for($setup['organization'])->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($setup['organization'])->for($staffUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);

    test()->actingAs($staffUser)->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'appointment_request_id' => $lead->id,
    ])->assertForbidden();

    expect($lead->fresh()->status)->toBe(AppointmentRequestStatus::Pending);
});
