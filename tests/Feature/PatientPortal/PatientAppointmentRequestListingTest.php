<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Enums\PatientUserLinkRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use App\Models\SiteService;
use Illuminate\Routing\Middleware\ThrottleRequests;

// O rate limit real (throttle:patient-portal-write) usa o cache Redis do
// ambiente de teste — mesmo padrão de PatientPortalAuthenticationTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('lists pending appointment requests waiting for confirmation, alongside real appointments', function () {
    $setup = patientPortalAppointmentSetup();
    $siteService = SiteService::factory()->create(['name' => 'Avaliação estética']);
    $request = AppointmentRequest::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->for($setup['professional'])
        ->create([
            'service_id' => $siteService->id,
            'status' => AppointmentRequestStatus::Pending,
            'appointment_id' => null,
            'preferred_date' => '2026-09-10',
            'preferred_period' => 'Manhã',
        ]);

    $response = $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/agendamentos");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('pendingRequests.0.id', $request->id)
        ->where('pendingRequests.0.status', 'pending')
        ->where('pendingRequests.0.status_label', 'Aguardando contato')
        ->where('pendingRequests.0.service_name', 'Avaliação estética')
        ->where('pendingRequests.0.professional_name', $setup['professional']->display_name)
        ->where('pendingRequests.0.preferred_date', '2026-09-10')
        ->where('pendingRequests.0.preferred_period', 'Manhã'));
});

it('never shows a pending appointment request once it has been converted into a real appointment', function () {
    $setup = patientPortalAppointmentSetup();
    $appointment = Appointment::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->for($setup['professional'])
        ->for($setup['service'])
        ->for($setup['unit'])
        ->create();
    AppointmentRequest::factory()
        ->for($setup['organization'])
        ->for($setup['patient'])
        ->create([
            'status' => AppointmentRequestStatus::Scheduled,
            'appointment_id' => $appointment->id,
        ]);

    $response = $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/agendamentos");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('pendingRequests', [])
        ->where('appointments.0.id', $appointment->id));
});

it('never shows another patient\'s pending appointment request', function () {
    $setup = patientPortalAppointmentSetup();
    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    AppointmentRequest::factory()->for($setup['organization'])->for($otherPatient)->create([
        'appointment_id' => null,
    ]);

    $response = $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/agendamentos");

    $response->assertOk()->assertInertia(fn ($page) => $page->where('pendingRequests', []));
});

it('shows the cancelled status label when the clinic declines the request', function () {
    $setup = patientPortalAppointmentSetup();
    AppointmentRequest::factory()->for($setup['organization'])->for($setup['patient'])->create([
        'status' => AppointmentRequestStatus::Cancelled,
        'appointment_id' => null,
    ]);

    $response = $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/agendamentos");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('pendingRequests.0.status', 'cancelled')
        ->where('pendingRequests.0.status_label', 'Cancelado'));
});

it('returns 404 when a patient account tries to view pending requests for a patient it does not manage', function () {
    $setup = patientPortalAppointmentSetup();
    $otherPatientUser = PatientUser::factory()->for($setup['organization'])->create();
    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()->for($otherPatientUser)->for($otherPatient, 'patient')->create([
        'organization_id' => $setup['organization']->id,
        'role' => PatientUserLinkRole::Self,
    ]);

    $this->actingAs($otherPatientUser, 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/agendamentos")
        ->assertNotFound();
});
