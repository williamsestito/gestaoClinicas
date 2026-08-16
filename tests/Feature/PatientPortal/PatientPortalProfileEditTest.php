<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Database\Factories\LegalEntityFactory;

function patientPortalProfileSetup(): array
{
    $organization = Organization::factory()->create();
    $patientUser = PatientUser::factory()->for($organization)->create();
    $patient = Patient::factory()->for($organization)->create();
    PatientUserLink::factory()->for($patientUser)->for($patient, 'patient')->create(['organization_id' => $organization->id]);

    return compact('organization', 'patientUser', 'patient');
}

it('updates the core fields of the linked patient', function () {
    $setup = patientPortalProfileSetup();

    $this->actingAs($setup['patientUser'], 'patient')->put("/portal/pacientes/{$setup['patient']->id}", [
        'name' => 'Novo Nome',
        'preferred_name' => 'Apelido',
        'phone' => '(47) 99696-1511',
        'whatsapp' => null,
        'email' => 'novo@example.com',
        'document' => null,
    ])->assertRedirect('/portal');

    $fresh = $setup['patient']->fresh();
    expect($fresh->name)->toBe('Novo Nome')
        ->and($fresh->preferred_name)->toBe('Apelido')
        ->and($fresh->email)->toBe('novo@example.com');
});

it('creates the address when the full block is provided', function () {
    $setup = patientPortalProfileSetup();

    $this->actingAs($setup['patientUser'], 'patient')->put("/portal/pacientes/{$setup['patient']->id}", [
        'name' => $setup['patient']->name,
        'address' => [
            'postal_code' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
    ])->assertRedirect('/portal');

    expect($setup['patient']->fresh()->address?->street)->toBe('Av. Paulista');
});

it('rejects a partially filled address block', function () {
    $setup = patientPortalProfileSetup();

    $this->actingAs($setup['patientUser'], 'patient')->put("/portal/pacientes/{$setup['patient']->id}", [
        'name' => $setup['patient']->name,
        'address' => [
            'street' => 'Av. Paulista',
        ],
    ])->assertSessionHasErrors('address.number');
});

it('rejects a document colliding with another patient without confirming the collision', function () {
    $setup = patientPortalProfileSetup();
    $cpf = LegalEntityFactory::validCpf();
    Patient::factory()->for($setup['organization'])->create(['document' => $cpf]);

    $response = $this->actingAs($setup['patientUser'], 'patient')->put("/portal/pacientes/{$setup['patient']->id}", [
        'name' => $setup['patient']->name,
        'document' => $cpf,
    ]);

    $response->assertSessionHasErrors('document');
    expect(session('errors')->get('document')[0])->not->toContain('Já existe um paciente');
});
