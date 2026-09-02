<?php

declare(strict_types=1);

use App\Enums\PatientUserLinkRole;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;

/**
 * @return array{setup: array<string, mixed>, patientUser: PatientUser}
 */
function medicalRecordPatientPortalSetup(): array
{
    $setup = medicalRecordSetup();

    $patientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($setup['patient'], 'patient')
        ->create(['organization_id' => $setup['organization']->id, 'role' => PatientUserLinkRole::Self]);

    return ['setup' => $setup, 'patientUser' => $patientUser];
}

it('shows a finalized and released medical record in the patient portal (RN-014)', function () {
    ['setup' => $setup, 'patientUser' => $patientUser] = medicalRecordPatientPortalSetup();
    MedicalRecord::factory()->releasedToPatient()->create([
        'appointment_id' => $setup['appointment']->id,
        'evaluation' => 'Tudo dentro do esperado.',
    ]);

    $response = $this->actingAs($patientUser, 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/prontuarios")
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->where('records.0.evaluation', 'Tudo dentro do esperado.'));
});

it('hides a finalized but not-yet-released medical record from the patient portal (RN-014)', function () {
    ['setup' => $setup, 'patientUser' => $patientUser] = medicalRecordPatientPortalSetup();
    MedicalRecord::factory()->finalized()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($patientUser, 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/prontuarios")
        ->assertInertia(fn ($page) => $page->where('records', []));
});

it('hides a draft medical record from the patient portal', function () {
    ['setup' => $setup, 'patientUser' => $patientUser] = medicalRecordPatientPortalSetup();
    MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($patientUser, 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/prontuarios")
        ->assertInertia(fn ($page) => $page->where('records', []));
});

it('blocks a patient from viewing another patient\'s medical records (not linked via PatientUserLink)', function () {
    ['setup' => $setup, 'patientUser' => $patientUser] = medicalRecordPatientPortalSetup();

    $strangerPatient = Patient::factory()->for($setup['organization'])->create();

    $this->actingAs($patientUser, 'patient')
        ->get("/portal/pacientes/{$strangerPatient->id}/prontuarios")
        ->assertNotFound();
});
