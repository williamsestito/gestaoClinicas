<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Patient;
use App\Models\Professional;

// patientSearchStaffSetup()/professionalSearchSetup() vivem em
// tests/Pest.php — compartilhadas com PatientSummaryTest.php.

it('lets a broad-access user (owner) find any patient in the organization by name', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create(['name' => 'Maria da Silva']);

    $this->actingAs($user)
        ->getJson('/settings/patients/search?q=Maria')
        ->assertOk()
        ->assertJsonPath('patients.0.id', $patient->id);
});

it('lets a self-service professional find only their own patient, never a colleague\'s', function () {
    [$user, $organization, $professional] = professionalSearchSetup();
    $ownPatient = Patient::factory()->for($organization)->create(['primary_professional_id' => $professional->id, 'name' => 'Paciente Próprio']);

    $colleague = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    Patient::factory()->for($organization)->create(['primary_professional_id' => $colleague->id, 'name' => 'Paciente de Colega']);

    $response = $this->actingAs($user)
        ->getJson('/settings/patients/search?q=paciente')
        ->assertOk();

    $response->assertJsonCount(1, 'patients')
        ->assertJsonPath('patients.0.id', $ownPatient->id);
});

it('blocks a user without any patient permission from searching', function () {
    [$user, $organization] = patientSearchStaffSetup(SystemRole::Finance);
    Patient::factory()->for($organization)->create(['name' => 'Maria da Silva']);

    $this->actingAs($user)
        ->getJson('/settings/patients/search?q=Maria')
        ->assertForbidden();
});

it('blocks a professional-role user with no linked professional record, even with the own-access permission', function () {
    [$user, $organization] = patientSearchStaffSetup(SystemRole::Professional);
    Patient::factory()->for($organization)->create(['name' => 'Maria da Silva']);

    $this->actingAs($user)
        ->getJson('/settings/patients/search?q=Maria')
        ->assertForbidden();
});

it('returns an empty list for a query shorter than 2 characters', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    Patient::factory()->for($organization)->create(['name' => 'A']);

    $this->actingAs($user)
        ->getJson('/settings/patients/search?q=a')
        ->assertOk()
        ->assertJsonCount(0, 'patients');
});
