<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientResponsible;

it('adds a responsible to a patient', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->post("/settings/patients/{$patient->id}/responsibles", [
        'name' => 'Responsável Um',
        'phone' => '11999990000',
        'relationship' => 'mãe',
        'is_legal_guardian' => true,
    ])->assertRedirect();

    expect(PatientResponsible::query()->where('patient_id', $patient->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('auditable_id', PatientResponsible::query()->first()->id)->exists())->toBeTrue();
});

it('rejects a responsible without any role selected', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->post("/settings/patients/{$patient->id}/responsibles", [
        'name' => 'Sem Papel',
        'phone' => '11999990000',
        'relationship' => 'tio',
    ])->assertSessionHasErrors('is_legal_guardian');
});

it('blocks removing the only legal guardian of a minor patient', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->minor()->for($user->organizationMemberships()->first()->organization)->create();
    $responsible = PatientResponsible::factory()->legalGuardian()->for($patient)->create([
        'organization_id' => $patient->organization_id,
    ]);

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}/responsibles/{$responsible->id}")
        ->assertSessionHasErrors('responsible');

    expect($responsible->fresh())->not->toBeNull();
});

it('allows removing a legal guardian of a minor patient when another one remains', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->minor()->for($user->organizationMemberships()->first()->organization)->create();
    $first = PatientResponsible::factory()->legalGuardian()->for($patient)->create(['organization_id' => $patient->organization_id]);
    PatientResponsible::factory()->legalGuardian()->for($patient)->create(['organization_id' => $patient->organization_id]);

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}/responsibles/{$first->id}")
        ->assertRedirect();

    expect($first->fresh()->trashed())->toBeTrue();
});

it('allows removing a non-legal-guardian responsible of a minor freely', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->minor()->for($user->organizationMemberships()->first()->organization)->create();
    PatientResponsible::factory()->legalGuardian()->for($patient)->create(['organization_id' => $patient->organization_id]);
    $financial = PatientResponsible::factory()->for($patient)->create([
        'organization_id' => $patient->organization_id,
        'is_legal_guardian' => false,
        'is_financial_responsible' => true,
    ]);

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}/responsibles/{$financial->id}")
        ->assertRedirect();

    expect($financial->fresh()->trashed())->toBeTrue();
});

it('blocks access to a responsible belonging to another patient even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patientA = Patient::factory()->for($organization)->create();
    $patientB = Patient::factory()->for($organization)->create();
    $responsibleOfB = PatientResponsible::factory()->for($patientB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/patients/{$patientA->id}/responsibles/{$responsibleOfB->id}")
        ->assertNotFound();
});

it('blocks access to a patient responsible from another organization', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $foreignPatient = Patient::factory()->for($otherOrganization)->create();

    $this->actingAs($user)->post("/settings/patients/{$foreignPatient->id}/responsibles", [
        'name' => 'Invasor',
        'phone' => '11999990000',
        'relationship' => 'mãe',
        'is_legal_guardian' => true,
    ])->assertNotFound();
});
