<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\PatientUserLinkRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\PatientUser;
use App\Models\PatientUserLink;

function patientPortalDashboardSetup(): array
{
    $setup = appointmentSetup();

    $patientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($setup['patient'], 'patient')
        ->create(['organization_id' => $setup['organization']->id, 'role' => PatientUserLinkRole::Self]);

    return [...$setup, 'patientUser' => $patientUser];
}

it('shows the soonest future appointment as the next one, ignoring cancelled/no-show/completed', function () {
    $setup = patientPortalDashboardSetup();

    Appointment::factory()->for($setup['organization'])->for($setup['patient'])
        ->for($setup['professional'])->for($setup['service'])->for($setup['unit'])
        ->create(['status' => AppointmentStatus::Cancelled, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30)]);
    $confirmed = Appointment::factory()->for($setup['organization'])->for($setup['patient'])
        ->for($setup['professional'])->for($setup['service'])->for($setup['unit'])
        ->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addMinutes(30)]);
    Appointment::factory()->for($setup['organization'])->for($setup['patient'])
        ->for($setup['professional'])->for($setup['service'])->for($setup['unit'])
        ->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addMinutes(30)]);

    $response = $this->actingAs($setup['patientUser'], 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('patients.0.next_appointment.starts_at', $confirmed->starts_at->toIso8601String())
        ->where('patients.0.next_appointment.status_label', 'Confirmado'));
});

it('shows the most recent completed appointment as the last one', function () {
    $setup = patientPortalDashboardSetup();

    Appointment::factory()->for($setup['organization'])->for($setup['patient'])
        ->for($setup['professional'])->for($setup['service'])->for($setup['unit'])
        ->create(['status' => AppointmentStatus::Completed, 'starts_at' => now()->subDays(30), 'ends_at' => now()->subDays(30)->addMinutes(30)]);
    $mostRecent = Appointment::factory()->for($setup['organization'])->for($setup['patient'])
        ->for($setup['professional'])->for($setup['service'])->for($setup['unit'])
        ->create(['status' => AppointmentStatus::Completed, 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(5)->addMinutes(30)]);

    $response = $this->actingAs($setup['patientUser'], 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('patients.0.last_appointment.starts_at', $mostRecent->starts_at->toIso8601String()));
});

it('counts only appointment requests not yet converted into a real appointment', function () {
    $setup = patientPortalDashboardSetup();
    AppointmentRequest::factory()->for($setup['organization'])->for($setup['patient'])->create(['appointment_id' => null]);
    $appointment = Appointment::factory()->for($setup['organization'])->for($setup['patient'])
        ->for($setup['professional'])->for($setup['service'])->for($setup['unit'])->create();
    AppointmentRequest::factory()->for($setup['organization'])->for($setup['patient'])->create(['appointment_id' => $appointment->id]);

    $response = $this->actingAs($setup['patientUser'], 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('patients.0.pending_requests_count', 1));
});

it('shares ownPatientId globally for the account\'s self-role patient', function () {
    $setup = patientPortalDashboardSetup();

    $response = $this->actingAs($setup['patientUser'], 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('patientPortal.ownPatientId', $setup['patient']->id));
});

it('leaves ownPatientId null for an account that only manages dependents', function () {
    $setup = appointmentSetup();
    $patientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($setup['patient'], 'patient')
        ->create(['organization_id' => $setup['organization']->id, 'role' => PatientUserLinkRole::Dependent]);

    $response = $this->actingAs($patientUser, 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('patientPortal.ownPatientId', null));
});

it('leaves patientPortal null for a staff (web guard) request', function () {
    $user = actingOwnerWithActiveContext();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('patientPortal', null));
});

it('resolves the clinic contact from the patient\'s preferred unit', function () {
    $setup = patientPortalDashboardSetup();
    $setup['unit']->update(['phone' => '(47) 3333-4444', 'whatsapp' => '(47) 99999-0000']);
    $setup['patient']->update(['preferred_unit_id' => $setup['unit']->id]);

    $response = $this->actingAs($setup['patientUser'], 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('clinicContact.name', $setup['unit']->name)
        ->where('clinicContact.phone', '(47) 3333-4444')
        ->where('clinicContact.whatsapp', '(47) 99999-0000'));
});

it('falls back to the organization headquarters when the patient has no preferred unit', function () {
    $setup = patientPortalDashboardSetup();
    $setup['patient']->update(['preferred_unit_id' => null]);
    $setup['unit']->update(['is_headquarters' => true, 'phone' => '(47) 3333-4444']);

    $response = $this->actingAs($setup['patientUser'], 'patient')->get('/portal');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('clinicContact.phone', '(47) 3333-4444'));
});
