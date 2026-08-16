<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

function createConfirmedAppointment(array $setup, ?Carbon $startsAt = null): Appointment
{
    // ->utc() é obrigatório: o cast 'datetime' do Eloquent serializa o
    // Carbon no fuso em que ele já está, sem converter sozinho (mesmo
    // cuidado do controller, ver AppointmentController::store()).
    $startsAt ??= Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->utc();

    return Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed,
    ]);
}

it('walks through the full lifecycle: confirmed -> checked-in -> in-progress -> completed', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup, Carbon::now()->subHour());

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/check-in")->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CheckedIn)
        ->and($appointment->fresh()->checked_in_at)->not->toBeNull();

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/start")->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::InProgress)
        ->and($appointment->fresh()->started_at)->not->toBeNull();

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/complete")->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed)
        ->and($appointment->fresh()->completed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $appointment->id)->count())->toBeGreaterThanOrEqual(3);
});

it('blocks starting an appointment that has not been checked in', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup, Carbon::now()->subHour());

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/start")
        ->assertSessionHasErrors('appointment');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('blocks completing an appointment that is not in progress', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup, Carbon::now()->subHour());

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/complete")
        ->assertSessionHasErrors('appointment');
});

it('cancels an appointment with a required reason', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup);

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/cancel", [])
        ->assertSessionHasErrors('reason');

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/cancel", [
        'reason' => 'Paciente remarcou por telefone.',
    ])->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->fresh()->cancellation_reason)->toBe('Paciente remarcou por telefone.');
});

it('blocks cancelling an appointment already in a final state', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup);
    $appointment->update(['status' => AppointmentStatus::Completed]);

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/cancel", [
        'reason' => 'Tarde demais',
    ])->assertSessionHasErrors('appointment');
});

it('blocks marking no-show before the appointment start time', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup, Carbon::now()->addHour());

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/no-show")
        ->assertSessionHasErrors('appointment');
});

it('allows marking no-show after the appointment start time has passed', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup, Carbon::now()->subHour());

    $this->actingAs($setup['user'])->patch("/settings/appointments/{$appointment->id}/no-show")
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow);
});

it('reschedules an appointment to a free slot and revalidates conflict', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup);
    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    $blocker = createConfirmedAppointment($setup, Carbon::parse('2026-08-03 11:00', 'America/Sao_Paulo')->utc());
    $blocker->update(['patient_id' => $otherPatient->id]);

    // Reagendar para um horário livre funciona.
    $this->actingAs($setup['user'])->put("/settings/appointments/{$appointment->id}/reschedule", [
        'starts_at' => '2026-08-03T10:00:00',
    ])->assertRedirect();

    expect($appointment->fresh()->starts_at->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('10:00');

    // Reagendar para cima do outro agendamento é bloqueado.
    $this->actingAs($setup['user'])->put("/settings/appointments/{$appointment->id}/reschedule", [
        'starts_at' => '2026-08-03T11:00:00',
    ])->assertSessionHasErrors('starts_at');
});

it('lets the linked professional check-in, start and complete their own appointment via manage-own, but not cancel it', function () {
    $setup = appointmentSetup();
    $appointment = createConfirmedAppointment($setup, Carbon::now()->subHour());

    $professionalUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $setup['professional']->update(['user_id' => $professionalUser->id]);

    $permission = Permission::query()->firstOrCreate(
        ['key' => PermissionKey::AppointmentsManageOwn->value],
        ['group' => PermissionKey::AppointmentsManageOwn->group(), 'label' => PermissionKey::AppointmentsManageOwn->label()],
    );
    $role = Role::factory()->for($setup['organization'])->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($setup['organization'])->for($professionalUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);

    $this->actingAs($professionalUser)->patch("/settings/appointments/{$appointment->id}/check-in")
        ->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CheckedIn);

    $this->actingAs($professionalUser)->patch("/settings/appointments/{$appointment->id}/cancel", [
        'reason' => 'Não deveria conseguir',
    ])->assertForbidden();
});
