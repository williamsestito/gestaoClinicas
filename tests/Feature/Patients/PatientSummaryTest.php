<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Models\Professional;

it('lets an admin (broad access) see the full summary, grouped by professional', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create(['email' => 'paciente@example.com']);

    $professionalA = Professional::factory()->for($organization)->create();
    $professionalB = Professional::factory()->for($organization)->create();
    Appointment::factory()->for($organization)->for($professionalA)->for($patient)->create(['status' => AppointmentStatus::Completed]);
    Appointment::factory()->for($organization)->for($professionalB)->for($patient)->create(['status' => AppointmentStatus::Confirmed]);
    AppointmentRequest::factory()->for($organization)->for($professionalA)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);

    $response = $this->actingAs($user)->getJson("/settings/patients/{$patient->id}/resumo");

    $response->assertOk()
        ->assertJsonPath('full_access', true)
        ->assertJsonPath('patient.email', 'paciente@example.com')
        ->assertJsonCount(2, 'appointments_by_professional')
        ->assertJsonCount(1, 'pending_requests');
});

it('lets a professional who already attended the patient (not the primary one) see the full summary', function () {
    [$user, $organization, $professional] = professionalSearchSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null]);
    Appointment::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentStatus::Completed]);

    $response = $this->actingAs($user)->getJson("/settings/patients/{$patient->id}/resumo");

    $response->assertOk()
        ->assertJsonPath('full_access', true)
        ->assertJsonCount(1, 'appointments_by_professional');
});

it('limits a professional with only a pending request to a summary, hiding personal fields', function () {
    [$user, $organization, $professional] = professionalSearchSetup();
    $patient = Patient::factory()->for($organization)->create([
        'primary_professional_id' => null,
        'email' => 'paciente@example.com',
    ]);
    AppointmentRequest::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);

    $response = $this->actingAs($user)->getJson("/settings/patients/{$patient->id}/resumo");

    $response->assertOk()
        ->assertJsonPath('full_access', false)
        ->assertJsonPath('patient.email', null)
        ->assertJsonPath('patient.birth_date', null)
        ->assertJsonCount(1, 'pending_requests');
});

it('never shows another professional\'s pending request for the same patient to a summary-only professional', function () {
    [$user, $organization, $professional] = professionalSearchSetup();
    $colleague = Professional::factory()->for($organization)->create();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null]);
    AppointmentRequest::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);
    AppointmentRequest::factory()->for($organization)->for($colleague)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);

    $response = $this->actingAs($user)->getJson("/settings/patients/{$patient->id}/resumo");

    $response->assertOk()
        ->assertJsonCount(1, 'pending_requests')
        ->assertJsonPath('pending_requests.0.professional_name', $professional->display_name);
});

it('blocks a professional with no relationship to the patient at all', function () {
    [$user, $organization] = professionalSearchSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null]);

    $this->actingAs($user)->getJson("/settings/patients/{$patient->id}/resumo")->assertForbidden();
});

it('never exposes medical-record access to someone who only has a pending request', function () {
    [$user, $organization, $professional] = professionalSearchSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null]);
    AppointmentRequest::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);

    $response = $this->actingAs($user)->getJson("/settings/patients/{$patient->id}/resumo");

    $response->assertOk()->assertJsonPath('can_view_medical_record', false);
});
