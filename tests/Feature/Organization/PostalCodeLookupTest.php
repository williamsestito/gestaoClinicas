<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function actingOwnerWithActiveContext(): User
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()
        ->owner()
        ->for($organization)
        ->for($user)
        ->create(['status' => OrganizationMembershipStatus::Active]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unit, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $unit->id]);

    return $user;
}

it('returns address data for a valid postal code', function () {
    Http::fake([
        'viacep.com.br/*' => Http::response([
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310100')
        ->assertOk()
        ->assertJson(['city' => 'São Paulo', 'state' => 'SP']);
});

it('returns 404 for a postal code that does not exist', function () {
    Http::fake([
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/00000000')->assertNotFound();
});

it('does not break when the external service times out', function () {
    Http::fake([
        'viacep.com.br/*' => fn () => throw new ConnectionException('timed out'),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310100')->assertNotFound();
});

it('caches a successful postal code lookup', function () {
    Http::fake([
        'viacep.com.br/*' => Http::response([
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310100')->assertOk();
    $this->actingAs($user)->getJson('/cep/01310100')->assertOk();

    Http::assertSentCount(1);
});

afterEach(function () {
    Cache::flush();
});
