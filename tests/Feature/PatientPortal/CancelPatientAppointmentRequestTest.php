<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('lets the patient cancel their own pending pre-agendamento', function () {
    $setup = patientPortalAppointmentSetup();
    $request = AppointmentRequest::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->create(['status' => AppointmentRequestStatus::Pending, 'appointment_id' => null]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->patch("/portal/pacientes/{$setup['patient']->id}/pre-agendamentos/{$request->id}/cancelar")
        ->assertRedirect("/portal/pacientes/{$setup['patient']->id}/agendamentos");

    expect($request->fresh()->status)->toBe(AppointmentRequestStatus::Cancelled);
});

it('lets the patient cancel a pre-agendamento that has already been contacted', function () {
    $setup = patientPortalAppointmentSetup();
    $request = AppointmentRequest::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->create(['status' => AppointmentRequestStatus::Contacted, 'appointment_id' => null]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->patch("/portal/pacientes/{$setup['patient']->id}/pre-agendamentos/{$request->id}/cancelar")
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(AppointmentRequestStatus::Cancelled);
});

it('rejects cancelling a pre-agendamento that is already cancelled', function () {
    $setup = patientPortalAppointmentSetup();
    $request = AppointmentRequest::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->create(['status' => AppointmentRequestStatus::Cancelled, 'appointment_id' => null]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->patch("/portal/pacientes/{$setup['patient']->id}/pre-agendamentos/{$request->id}/cancelar")
        ->assertSessionHasErrors('appointmentRequest');
});

it('returns 404 when trying to cancel a pre-agendamento already converted into a real appointment', function () {
    $setup = patientPortalAppointmentSetup();
    $appointment = Appointment::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->for($setup['professional'])
        ->for($setup['service'])
        ->for($setup['unit'])
        ->create();
    $request = AppointmentRequest::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->create(['status' => AppointmentRequestStatus::Scheduled, 'appointment_id' => $appointment->id]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->patch("/portal/pacientes/{$setup['patient']->id}/pre-agendamentos/{$request->id}/cancelar")
        ->assertNotFound();

    expect($request->fresh()->status)->toBe(AppointmentRequestStatus::Scheduled);
});

it('returns 404 when a patient account tries to cancel another patient\'s pre-agendamento', function () {
    $setup = patientPortalAppointmentSetup();
    $otherOrganization = $setup['organization'];
    $otherPatient = Patient::factory()->for($otherOrganization)->create();
    $request = AppointmentRequest::factory()
        ->for($otherOrganization)
        ->for($otherPatient)
        ->create(['status' => AppointmentRequestStatus::Pending, 'appointment_id' => null]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->patch("/portal/pacientes/{$setup['patient']->id}/pre-agendamentos/{$request->id}/cancelar")
        ->assertNotFound();

    expect($request->fresh()->status)->toBe(AppointmentRequestStatus::Pending);
});
