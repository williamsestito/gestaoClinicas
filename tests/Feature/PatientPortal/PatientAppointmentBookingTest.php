<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\PatientUserLinkRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Routing\Middleware\ThrottleRequests;

// O rate limit real (throttle:patient-portal-write) usa o cache Redis do
// ambiente de teste — mesmo padrão de PatientPortalAuthenticationTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

function patientPortalAppointmentSetup(): array
{
    $setup = appointmentSetup();

    $patientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($setup['patient'], 'patient')
        ->create(['organization_id' => $setup['organization']->id, 'role' => PatientUserLinkRole::Self]);

    return [...$setup, 'patientUser' => $patientUser];
}

it('lists available slots for the portal without requiring the patient in the URL', function () {
    $setup = patientPortalAppointmentSetup();

    $response = $this->actingAs($setup['patientUser'], 'patient')->getJson(
        '/portal/agendamentos/horarios?'.http_build_query([
            'unit_id' => $setup['unit']->id,
            'professional_id' => $setup['professional']->id,
            'service_id' => $setup['service']->id,
            'date' => appointmentMonday()->toDateString(),
        ]),
    );

    $response->assertOk();
    expect($response->json('slots'))->not->toBeEmpty();
});

it('lets the patient book a real appointment that enters as requested, not confirmed', function () {
    $setup = patientPortalAppointmentSetup();

    $this->actingAs($setup['patientUser'], 'patient')->post(
        "/portal/pacientes/{$setup['patient']->id}/agendamentos",
        [
            'unit_id' => $setup['unit']->id,
            'professional_id' => $setup['professional']->id,
            'service_id' => $setup['service']->id,
            'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        ],
    )->assertRedirect("/portal/pacientes/{$setup['patient']->id}/agendamentos");

    $appointment = $setup['patient']->fresh()->appointments()->first();
    expect($appointment)->not->toBeNull()
        ->and($appointment->status)->toBe(AppointmentStatus::Requested);

    $log = AuditLog::query()->where('auditable_id', $appointment->id)->firstOrFail();
    expect($log->after_data['booked_by'])->toBe('patient_portal')
        ->and($log->after_data['patient_user_id'])->toBe($setup['patientUser']->id)
        ->and($log->actor_user_id)->toBeNull();
});

it('blocks a portal booking that conflicts with an existing appointment, even one still requested', function () {
    $setup = patientPortalAppointmentSetup();

    $this->actingAs($setup['patientUser'], 'patient')->post(
        "/portal/pacientes/{$setup['patient']->id}/agendamentos",
        [
            'unit_id' => $setup['unit']->id,
            'professional_id' => $setup['professional']->id,
            'service_id' => $setup['service']->id,
            'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        ],
    )->assertRedirect();

    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    $otherPatientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($otherPatientUser)
        ->for($otherPatient, 'patient')
        ->create(['organization_id' => $setup['organization']->id, 'role' => PatientUserLinkRole::Self]);

    $this->actingAs($otherPatientUser, 'patient')->post(
        "/portal/pacientes/{$otherPatient->id}/agendamentos",
        [
            'unit_id' => $setup['unit']->id,
            'professional_id' => $setup['professional']->id,
            'service_id' => $setup['service']->id,
            'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        ],
    )->assertSessionHasErrors();

    expect(Appointment::query()->where('professional_id', $setup['professional']->id)->count())->toBe(1);
});

it('returns 404 when a patient account tries to book for a patient it does not manage', function () {
    $setup = patientPortalAppointmentSetup();
    $otherPatient = Patient::factory()->for($setup['organization'])->create();

    $this->actingAs($setup['patientUser'], 'patient')->post(
        "/portal/pacientes/{$otherPatient->id}/agendamentos",
        [
            'unit_id' => $setup['unit']->id,
            'professional_id' => $setup['professional']->id,
            'service_id' => $setup['service']->id,
            'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        ],
    )->assertNotFound();
});

it('blocks an unauthenticated request from booking an appointment', function () {
    $setup = patientPortalAppointmentSetup();

    $this->post("/portal/pacientes/{$setup['patient']->id}/agendamentos", [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
    ])->assertRedirect('/login');
});
