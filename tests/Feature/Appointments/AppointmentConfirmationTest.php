<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Appointment;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

function createRequestedAppointment(array $setup, ?Carbon $startsAt = null): Appointment
{
    $startsAt ??= Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->utc();

    return Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addMinutes(30),
        'status' => AppointmentStatus::Requested,
    ]);
}

it('lets staff with appointments.manage confirm a requested appointment', function () {
    $setup = appointmentSetup();
    $appointment = createRequestedAppointment($setup);

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/confirm")
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('blocks confirming an appointment that is not requested', function () {
    $setup = appointmentSetup();
    $appointment = createRequestedAppointment($setup);
    $appointment->update(['status' => AppointmentStatus::Confirmed]);

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/confirm")
        ->assertSessionHasErrors('appointment');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('blocks a user without appointments.manage from confirming', function () {
    $setup = appointmentSetup();
    $appointment = createRequestedAppointment($setup);

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

    $this->actingAs($staffUser)->patch("/settings/appointments/{$appointment->id}/confirm")
        ->assertForbidden();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Requested);
});

it('lets staff decline (cancel) a requested appointment', function () {
    $setup = appointmentSetup();
    $appointment = createRequestedAppointment($setup);

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/cancel", [
        'reason' => 'Horário indisponível para a clínica.',
    ])->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});
