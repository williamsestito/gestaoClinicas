<?php

declare(strict_types=1);

use App\Actions\Organization\DeletePatientAction;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;

function twoPortalAccounts(): array
{
    $organization = Organization::factory()->create();

    $accountA = PatientUser::factory()->for($organization)->create();
    $patientA = Patient::factory()->for($organization)->create();
    PatientUserLink::factory()->for($accountA)->for($patientA, 'patient')->create(['organization_id' => $organization->id]);

    $accountB = PatientUser::factory()->for($organization)->create();
    $patientB = Patient::factory()->for($organization)->create();
    PatientUserLink::factory()->for($accountB)->for($patientB, 'patient')->create(['organization_id' => $organization->id]);

    return compact('organization', 'accountA', 'patientA', 'accountB', 'patientB');
}

it('returns 404 when an account tries to view a patient linked only to another account', function () {
    $setup = twoPortalAccounts();

    $this->actingAs($setup['accountA'], 'patient')
        ->get("/portal/pacientes/{$setup['patientB']->id}/editar")
        ->assertNotFound();
});

it('returns 404 when an account tries to update a patient linked only to another account', function () {
    $setup = twoPortalAccounts();

    $this->actingAs($setup['accountA'], 'patient')
        ->put("/portal/pacientes/{$setup['patientB']->id}", [
            'name' => 'Nome Hostil',
        ])
        ->assertNotFound();

    expect($setup['patientB']->fresh()->name)->not->toBe('Nome Hostil');
});

it('never lists another account\'s patients on the dashboard', function () {
    $setup = twoPortalAccounts();

    $response = $this->actingAs($setup['accountA'], 'patient')->get('/portal');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('patient-portal/Dashboard')
        ->has('patients', 1)
        ->where('patients.0.id', $setup['patientA']->id));
});

it('lets an account view and edit its own linked patient', function () {
    $setup = twoPortalAccounts();

    $this->actingAs($setup['accountA'], 'patient')
        ->get("/portal/pacientes/{$setup['patientA']->id}/editar")
        ->assertOk();

    $this->actingAs($setup['accountA'], 'patient')
        ->put("/portal/pacientes/{$setup['patientA']->id}", [
            'name' => 'Nome Atualizado',
            'phone' => null,
            'whatsapp' => null,
            'email' => null,
            'document' => null,
            'preferred_name' => null,
        ])
        ->assertRedirect('/portal');

    expect($setup['patientA']->fresh()->name)->toBe('Nome Atualizado');
});

it('does not crash the dashboard when a linked patient has been soft-deleted by staff, and frees the link so it never lists it', function () {
    $setup = twoPortalAccounts();

    app(DeletePatientAction::class)->handle($setup['patientA']);

    $response = $this->actingAs($setup['accountA'], 'patient')->get('/portal');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('patient-portal/Dashboard')
        ->has('patients', 0));

    expect(PatientUserLink::withTrashed()->where('patient_id', $setup['patientA']->id)->firstOrFail()->trashed())->toBeTrue();
});
