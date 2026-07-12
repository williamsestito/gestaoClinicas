<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\User;
use Database\Factories\LegalEntityFactory;

function baseOnboardingAddressPayload(array $addressOverrides = []): array
{
    return [
        'organization_name' => 'Clínica Teste',
        'legal_entity_type' => 'individual',
        'document' => LegalEntityFactory::validCpf(),
        'legal_name' => 'Fulano de Tal',
        'trade_name' => null,
        'unit_name' => 'Unidade Única',
        'unit_phone' => null,
        'unit_whatsapp' => null,
        'address' => array_merge([
            'postal_code' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'complement' => null,
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ], $addressOverrides),
        'opening_hours' => [],
    ];
}

it('requires street, number, neighborhood and city', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/onboarding/organization', baseOnboardingAddressPayload([
            'street' => '',
            'number' => '',
            'neighborhood' => '',
            'city' => '',
        ]))
        ->assertSessionHasErrors([
            'address.street', 'address.number', 'address.neighborhood', 'address.city',
        ]);
});

it('allows complement to be empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/onboarding/organization', baseOnboardingAddressPayload(['complement' => null]))
        ->assertSessionDoesntHaveErrors('address.complement');
});

it('rejects an invalid Brazilian state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/onboarding/organization', baseOnboardingAddressPayload(['state' => 'ZZ']))
        ->assertSessionHasErrors('address.state');
});

it('keeps addresses isolated per organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $entityA = LegalEntity::factory()->for($orgA)->create();
    $entityB = LegalEntity::factory()->for($orgB)->create();

    $addressA = Address::factory()->for($orgA)->for($entityA, 'addressable')->create();
    $addressB = Address::factory()->for($orgB)->for($entityB, 'addressable')->create();

    $addressesForOrgA = Address::query()->where('organization_id', $orgA->id)->pluck('id');

    expect($addressesForOrgA)->toHaveCount(1)
        ->and($addressesForOrgA->first())->toBe($addressA->id)
        ->and($addressesForOrgA)->not->toContain($addressB->id);
});
