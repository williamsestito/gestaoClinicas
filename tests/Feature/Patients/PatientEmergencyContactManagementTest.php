<?php

declare(strict_types=1);

use App\Models\Patient;
use App\Models\PatientEmergencyContact;

it('adds an emergency contact to a patient', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();
    PatientEmergencyContact::factory()->for($patient)->create(['organization_id' => $patient->organization_id]);

    $this->actingAs($user)->post("/settings/patients/{$patient->id}/emergency-contacts", [
        'name' => 'Novo Contato',
        'relationship' => 'amigo(a)',
        'phone_primary' => '11999990000',
    ])->assertRedirect();

    expect(PatientEmergencyContact::query()->where('patient_id', $patient->id)->count())->toBe(2);
});

it('blocks removing the last emergency contact of a patient', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();
    $contact = PatientEmergencyContact::factory()->for($patient)->create(['organization_id' => $patient->organization_id]);

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}/emergency-contacts/{$contact->id}")
        ->assertSessionHasErrors('contact');

    expect($contact->fresh())->not->toBeNull();
});

it('allows removing an emergency contact when another one remains', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();
    $first = PatientEmergencyContact::factory()->for($patient)->create(['organization_id' => $patient->organization_id]);
    PatientEmergencyContact::factory()->for($patient)->create(['organization_id' => $patient->organization_id]);

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}/emergency-contacts/{$first->id}")
        ->assertRedirect();

    expect($first->fresh()->trashed())->toBeTrue();
});

it('blocks access to an emergency contact belonging to another patient even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patientA = Patient::factory()->for($organization)->create();
    $patientB = Patient::factory()->for($organization)->create();
    $contactOfB = PatientEmergencyContact::factory()->for($patientB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/patients/{$patientA->id}/emergency-contacts/{$contactOfB->id}")
        ->assertNotFound();
});
