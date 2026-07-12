<?php

declare(strict_types=1);

use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Database\QueryException;

function ownerActingInOrganization(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    return compact('organization', 'legalEntity', 'headquarters', 'user', 'membership');
}

it('enforces a unique unit code per organization', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Sul',
        'code' => $ctx['headquarters']->code,
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [],
    ])->assertSessionHasErrors('code');
});

it('only allows one headquarters per organization at the database level', function () {
    $ctx = ownerActingInOrganization();

    expect(fn () => Unit::factory()->headquarters()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create())
        ->toThrow(QueryException::class);
});

it('rejects an opening hours request with overlapping intervals on the same day', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Sul',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [
            ['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00'],
            ['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '18:00'],
        ],
    ])->assertSessionHasErrors('opening_hours');
});

it('rejects opening hours where closing time is not after opening time', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Sul',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [
            ['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '08:00'],
        ],
    ])->assertSessionHasErrors('opening_hours.0.closes_at');
});

it('creates a new unit successfully with valid, non-overlapping data', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Sul',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [
            ['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00'],
            ['day_of_week' => 1, 'opens_at' => '13:00', 'closes_at' => '18:00'],
        ],
    ])->assertRedirect('/settings/units');

    expect(Unit::query()->where('organization_id', $ctx['organization']->id)->where('name', 'Unidade Sul')->exists())->toBeTrue();
});
