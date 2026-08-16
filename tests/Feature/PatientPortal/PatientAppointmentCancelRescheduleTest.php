<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Routing\Middleware\ThrottleRequests;

// O rate limit real (throttle:patient-portal-write) usa o cache Redis do
// ambiente de teste — mesmo padrão de PatientPortalAuthenticationTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('lets the patient cancel their own confirmed appointment', function () {
    $setup = patientPortalAppointmentSetup();
    $appointment = createConfirmedAppointment($setup);

    $this->actingAs($setup['patientUser'], 'patient')->patch(
        "/portal/pacientes/{$setup['patient']->id}/agendamentos/{$appointment->id}/cancelar",
        ['reason' => 'Não posso mais comparecer'],
    )->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(AuditLog::query()->where('auditable_id', $appointment->id)->where('action', 'updated')->exists())->toBeTrue();
});

it('lets the patient reschedule their own appointment to a free slot', function () {
    $setup = patientPortalAppointmentSetup();
    $appointment = createConfirmedAppointment($setup);

    $this->actingAs($setup['patientUser'], 'patient')->put(
        "/portal/pacientes/{$setup['patient']->id}/agendamentos/{$appointment->id}/reagendar",
        ['starts_at' => appointmentMonday()->toDateString().'T10:00:00'],
    )->assertRedirect();

    expect($appointment->fresh()->starts_at->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('10:00');
});

it('blocks cancelling/rescheduling an appointment belonging to another account', function () {
    $setup = patientPortalAppointmentSetup();
    $appointment = createConfirmedAppointment($setup);

    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    $otherPatientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($otherPatientUser)
        ->for($otherPatient, 'patient')
        ->create(['organization_id' => $setup['organization']->id]);

    $this->actingAs($otherPatientUser, 'patient')->patch(
        "/portal/pacientes/{$otherPatient->id}/agendamentos/{$appointment->id}/cancelar",
        ['reason' => 'Tentativa indevida'],
    )->assertNotFound();

    $this->actingAs($otherPatientUser, 'patient')->put(
        "/portal/pacientes/{$otherPatient->id}/agendamentos/{$appointment->id}/reagendar",
        ['starts_at' => appointmentMonday()->toDateString().'T10:00:00'],
    )->assertNotFound();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('blocks cancelling an appointment already in a final state', function () {
    $setup = patientPortalAppointmentSetup();
    $appointment = createConfirmedAppointment($setup);
    $appointment->update(['status' => AppointmentStatus::Completed]);

    $this->actingAs($setup['patientUser'], 'patient')->patch(
        "/portal/pacientes/{$setup['patient']->id}/agendamentos/{$appointment->id}/cancelar",
        ['reason' => 'Tarde demais'],
    )->assertSessionHasErrors('appointment');
});
