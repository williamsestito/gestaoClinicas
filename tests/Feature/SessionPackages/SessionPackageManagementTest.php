<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\SessionPackage;
use Illuminate\Support\Carbon;

it('creates a session package for a patient', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create();

    $this->actingAs($user)->post("/settings/patients/{$patient->id}/session-packages", [
        'total_sessions' => 10,
    ])->assertRedirect();

    $package = SessionPackage::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($package->total_sessions)->toBe(10)
        ->and($package->status)->toBe(RecordStatus::Active)
        ->and($package->remainingSessions())->toBe(10);
});

it('computes remaining sessions from completed appointments, never from a stored counter', function () {
    $setup = appointmentSetup();
    $package = SessionPackage::factory()->for($setup['organization'])->for($setup['patient'])->create(['total_sessions' => 3]);

    Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'session_package_id' => $package->id,
        'status' => AppointmentStatus::Completed,
    ]);

    Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'session_package_id' => $package->id,
        'status' => AppointmentStatus::Confirmed,
    ]);

    expect($package->remainingSessions())->toBe(2);
});

it('blocks booking against an exhausted session package', function () {
    $setup = appointmentSetup();
    $package = SessionPackage::factory()->for($setup['organization'])->for($setup['patient'])->create(['total_sessions' => 1]);
    Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'session_package_id' => $package->id,
        'status' => AppointmentStatus::Completed,
    ]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'session_package_id' => $package->id,
    ])->assertSessionHasErrors('session_package_id');
});

it('blocks booking against an expired session package', function () {
    $setup = appointmentSetup();
    $package = SessionPackage::factory()->for($setup['organization'])->for($setup['patient'])->create([
        'total_sessions' => 10,
        'expires_at' => Carbon::yesterday(),
    ]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'session_package_id' => $package->id,
    ])->assertSessionHasErrors('session_package_id');
});

it('blocks booking against a session package belonging to another patient', function () {
    $setup = appointmentSetup();
    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    $package = SessionPackage::factory()->for($setup['organization'])->for($otherPatient)->create(['total_sessions' => 10]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'session_package_id' => $package->id,
    ])->assertSessionHasErrors('session_package_id');
});

it('links a successful booking to the chosen session package', function () {
    $setup = appointmentSetup();
    $package = SessionPackage::factory()->for($setup['organization'])->for($setup['patient'])->create(['total_sessions' => 10]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'session_package_id' => $package->id,
    ])->assertRedirect();

    $appointment = Appointment::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    expect($appointment->session_package_id)->toBe($package->id);
});

it('closes a session package manually, blocking further use but preserving history', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create();
    $package = SessionPackage::factory()->for($organization)->for($patient)->create();

    $this->actingAs($user)->patch("/settings/patients/{$patient->id}/session-packages/{$package->id}/close")
        ->assertRedirect();

    expect($package->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('blocks closing a session package belonging to another patient even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create();
    $otherPatient = Patient::factory()->for($organization)->create();
    $package = SessionPackage::factory()->for($organization)->for($otherPatient)->create();

    $this->actingAs($user)->patch("/settings/patients/{$patient->id}/session-packages/{$package->id}/close")
        ->assertNotFound();
});
