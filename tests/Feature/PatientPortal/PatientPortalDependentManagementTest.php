<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use App\Models\PatientResponsible;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Support\Carbon;

it('lets an authenticated account add a minor dependent, creating a legal guardian responsible', function () {
    $organization = Organization::factory()->create();
    $patientUser = PatientUser::factory()->for($organization)->create();

    $this->actingAs($patientUser, 'patient')->post('/portal/dependentes', [
        'name' => 'Bebê Recém',
        'birth_date' => Carbon::now()->subYears(2)->toDateString(),
        'relationship' => 'Mãe',
        'responsible_phone' => '(47) 99696-1511',
    ])->assertRedirect('/portal');

    $link = PatientUserLink::query()->where('patient_user_id', $patientUser->id)->firstOrFail();
    expect($link->role->value)->toBe('dependent');

    $patient = Patient::query()->findOrFail($link->patient_id);
    expect($patient->organization_id)->toBe($organization->id)
        ->and(PatientResponsible::query()->where('patient_id', $patient->id)->where('is_legal_guardian', true)->count())->toBe(1)
        ->and(PatientEmergencyContact::query()->where('patient_id', $patient->id)->count())->toBe(1);
});

it('lets an account add a second dependent alongside an existing self-link', function () {
    $organization = Organization::factory()->create();
    $patientUser = PatientUser::factory()->for($organization)->create();
    $selfPatient = Patient::factory()->for($organization)->create();
    PatientUserLink::factory()->for($patientUser)->for($selfPatient, 'patient')->create(['organization_id' => $organization->id]);

    $this->actingAs($patientUser, 'patient')->post('/portal/dependentes', [
        'name' => 'Segundo Filho',
        'birth_date' => Carbon::now()->subYears(5)->toDateString(),
        'relationship' => 'Pai',
        'responsible_phone' => '(47) 99696-1511',
    ])->assertRedirect('/portal');

    expect(PatientUserLink::query()->where('patient_user_id', $patientUser->id)->count())->toBe(2);
});

it('blocks an unauthenticated request from adding a dependent', function () {
    Organization::factory()->create();

    $this->post('/portal/dependentes', [
        'name' => 'Alguém',
        'birth_date' => Carbon::now()->subYears(5)->toDateString(),
        'relationship' => 'Mãe',
        'responsible_phone' => '(47) 99696-1511',
    ])->assertRedirect('/login');
});
