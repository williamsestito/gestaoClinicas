<?php

declare(strict_types=1);

use App\Models\Patient;
use Database\Factories\LegalEntityFactory;

// patientPortalProfileSetup() vive em tests/Pest.php — compartilhada com
// outro arquivo deste diretório.

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

it('allows saving a document that already belonged to an archived (soft-deleted) patient', function () {
    $setup = patientPortalProfileSetup();
    $archived = Patient::factory()->for($setup['organization'])->create(['document' => '52998224725']);
    $archived->delete();

    $this->actingAs($setup['patientUser'], 'patient')->put("/portal/pacientes/{$setup['patient']->id}", [
        'name' => $setup['patient']->name,
        'document' => '529.982.247-25',
    ])->assertSessionHasNoErrors();

    expect($setup['patient']->fresh()->document)->toBe('52998224725');
});

it('allows re-saving the patient\'s own unchanged document even when an unrelated archived patient shares it', function () {
    $setup = patientPortalProfileSetup();
    $archived = Patient::factory()->for($setup['organization'])->create(['document' => '52998224725']);
    $archived->delete();
    // Só depois do arquivamento: os dois nunca podem estar ativos com o
    // mesmo documento ao mesmo tempo (índice único parcial do banco).
    $setup['patient']->update(['document' => '52998224725']);

    $this->actingAs($setup['patientUser'], 'patient')->put("/portal/pacientes/{$setup['patient']->id}", [
        'name' => $setup['patient']->name,
        'document' => '529.982.247-25',
    ])->assertSessionHasNoErrors();
});
