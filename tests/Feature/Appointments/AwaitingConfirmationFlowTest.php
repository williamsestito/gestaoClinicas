<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

function requestedAppointmentWithPatientAccount(): array
{
    $setup = appointmentSetup();
    $patientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($setup['patient'], 'patient')
        ->create(['organization_id' => $setup['organization']->id]);

    $appointment = createConfirmedAppointment($setup);
    $appointment->update(['status' => AppointmentStatus::Requested]);

    return [...$setup, 'patientUser' => $patientUser, 'appointment' => $appointment->fresh()];
}

it('lets staff propose an alternate time, moving the appointment to awaiting_confirmation', function () {
    $ctx = requestedAppointmentWithPatientAccount();

    $this->actingAs($ctx['user'])->put("/settings/appointments/{$ctx['appointment']->id}/propose", [
        'starts_at' => appointmentMonday()->toDateString().'T11:00:00',
    ])->assertRedirect();

    $fresh = $ctx['appointment']->fresh();
    expect($fresh->status)->toBe(AppointmentStatus::AwaitingConfirmation)
        ->and($fresh->starts_at->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('11:00');
});

it('blocks proposing an alternate time for an appointment that is not requested', function () {
    $ctx = requestedAppointmentWithPatientAccount();
    $ctx['appointment']->update(['status' => AppointmentStatus::Confirmed]);

    $this->actingAs($ctx['user'])->put("/settings/appointments/{$ctx['appointment']->id}/propose", [
        'starts_at' => appointmentMonday()->toDateString().'T11:00:00',
    ])->assertSessionHasErrors('appointment');
});

it('lets the patient accept a proposed time, confirming the appointment', function () {
    $ctx = requestedAppointmentWithPatientAccount();
    $ctx['appointment']->update(['status' => AppointmentStatus::AwaitingConfirmation]);

    $this->actingAs($ctx['patientUser'], 'patient')->patch(
        "/portal/pacientes/{$ctx['patient']->id}/agendamentos/{$ctx['appointment']->id}/aceitar",
    )->assertRedirect();

    expect($ctx['appointment']->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('blocks accepting a proposed time for an appointment that is not awaiting confirmation', function () {
    $ctx = requestedAppointmentWithPatientAccount();

    $this->actingAs($ctx['patientUser'], 'patient')->patch(
        "/portal/pacientes/{$ctx['patient']->id}/agendamentos/{$ctx['appointment']->id}/aceitar",
    )->assertSessionHasErrors('appointment');
});

it('lets the patient decline a proposed time, cancelling the appointment', function () {
    $ctx = requestedAppointmentWithPatientAccount();
    $ctx['appointment']->update(['status' => AppointmentStatus::AwaitingConfirmation]);

    $this->actingAs($ctx['patientUser'], 'patient')->patch(
        "/portal/pacientes/{$ctx['patient']->id}/agendamentos/{$ctx['appointment']->id}/cancelar",
        ['reason' => 'Horário proposto recusado'],
    )->assertRedirect();

    expect($ctx['appointment']->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('blocks a patient account from accepting a proposed time for another account\'s appointment', function () {
    $ctx = requestedAppointmentWithPatientAccount();
    $ctx['appointment']->update(['status' => AppointmentStatus::AwaitingConfirmation]);

    $otherPatient = Patient::factory()->for($ctx['organization'])->create();
    $otherPatientUser = PatientUser::factory()->for($ctx['organization'])->create();
    PatientUserLink::factory()
        ->for($otherPatientUser)
        ->for($otherPatient, 'patient')
        ->create(['organization_id' => $ctx['organization']->id]);

    $this->actingAs($otherPatientUser, 'patient')->patch(
        "/portal/pacientes/{$otherPatient->id}/agendamentos/{$ctx['appointment']->id}/aceitar",
    )->assertNotFound();
});
