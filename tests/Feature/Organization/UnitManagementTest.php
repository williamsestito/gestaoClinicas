<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Database\QueryException;

// ownerActingInOrganization()/validUnitAddressPayload() vivem em
// tests/Pest.php — compartilhadas com vários outros arquivos de
// Organization/Sales/Roles.

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

it('grants the creating owner an active unit membership on the new unit', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Norte',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [],
    ])->assertRedirect('/settings/units');

    $unit = Unit::query()->where('organization_id', $ctx['organization']->id)->where('name', 'Unidade Norte')->firstOrFail();

    expect(
        UnitMembership::query()
            ->where('organization_membership_id', $ctx['membership']->id)
            ->where('unit_id', $unit->id)
            ->where('status', RecordStatus::Active)
            ->where('is_manager', true)
            ->exists(),
    )->toBeTrue();

    $this->actingAs($ctx['user'])
        ->get("/settings/units/{$unit->id}/edit")
        ->assertOk();
});

it('generates unique unit codes even when the naive count-based candidate collides', function () {
    $ctx = ownerActingInOrganization();

    // Organização já tem a matriz (1 unidade) + esta: o próximo candidato
    // "ingênuo" (contagem + 1) calculado na criação abaixo será 'UN-0003' —
    // forçando o retry do gerador de código a pular para 'UN-0004'.
    Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create(['code' => 'UN-0003']);

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Leste',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [],
    ])->assertRedirect('/settings/units');

    $unit = Unit::query()->where('organization_id', $ctx['organization']->id)->where('name', 'Unidade Leste')->firstOrFail();

    expect($unit->code)->not->toBe('UN-0003');
});

it('blocks access to a unit the user has no active unit membership for', function () {
    $ctx = ownerActingInOrganization();

    $otherUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create();

    $this->actingAs($ctx['user'])
        ->get("/settings/units/{$otherUnit->id}/edit")
        ->assertForbidden();
});

it('blocks access to a unit belonging to another organization even with a tampered URL', function () {
    $ctx = ownerActingInOrganization();

    $otherOrganization = Organization::factory()->create();
    $otherLegalEntity = LegalEntity::factory()->primary()->for($otherOrganization)->create();
    $foreignUnit = Unit::factory()->for($otherOrganization)->for($otherLegalEntity, 'legalEntity')->create();

    $this->actingAs($ctx['user'])
        ->get("/settings/units/{$foreignUnit->id}/edit")
        ->assertNotFound();
});

it('blocks access to an inactive unit even with an active unit membership', function () {
    $ctx = ownerActingInOrganization();

    $ctx['headquarters']->update(['status' => RecordStatus::Inactive]);

    $this->actingAs($ctx['user'])
        ->get("/settings/units/{$ctx['headquarters']->id}/edit")
        ->assertForbidden();
});

it('blocks access to a unit when the user unit membership is inactive', function () {
    $ctx = ownerActingInOrganization();

    UnitMembership::query()
        ->where('organization_membership_id', $ctx['membership']->id)
        ->where('unit_id', $ctx['headquarters']->id)
        ->update(['status' => RecordStatus::Inactive]);

    $this->actingAs($ctx['user'])
        ->get("/settings/units/{$ctx['headquarters']->id}/edit")
        ->assertForbidden();
});

it('redirects operational routes to unit selection when there is no active unit resolved', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unitA = Unit::factory()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $unitB = Unit::factory()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unitA, 'unit')->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unitB, 'unit')->create();

    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('context.unit.edit'));
});

it('redirects operational routes to unit selection when the active unit becomes inactive', function () {
    $ctx = ownerActingInOrganization();

    $ctx['headquarters']->update(['status' => RecordStatus::Inactive]);

    $this->actingAs($ctx['user'])
        ->get('/dashboard')
        ->assertRedirect(route('context.unit.edit'));
});

it('refuses to delete the headquarters unit', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->delete("/settings/units/{$ctx['headquarters']->id}")
        ->assertSessionHasErrors('unit');

    expect($ctx['headquarters']->fresh()->trashed())->toBeFalse();
});

it('logically deletes a non-headquarters unit and preserves its history', function () {
    $ctx = ownerActingInOrganization();
    $secondUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create();
    UnitMembership::factory()->for($ctx['membership'], 'organizationMembership')->for($secondUnit, 'unit')->create();

    $this->actingAs($ctx['user'])
        ->delete("/settings/units/{$secondUnit->id}")
        ->assertRedirect();

    expect($secondUnit->fresh()->trashed())->toBeTrue()
        ->and(Unit::query()->find($secondUnit->id))->toBeNull()
        ->and(Unit::withTrashed()->find($secondUnit->id))->not->toBeNull();
});

it('restores a logically deleted unit', function () {
    $ctx = ownerActingInOrganization();
    $secondUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create();
    $secondUnit->delete();

    $this->actingAs($ctx['user'])
        ->post("/settings/units/{$secondUnit->id}/restore")
        ->assertRedirect();

    expect($secondUnit->fresh()->trashed())->toBeFalse();
});

it('atomically swaps the headquarters unit', function () {
    $ctx = ownerActingInOrganization();
    $secondUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create();
    UnitMembership::factory()->for($ctx['membership'], 'organizationMembership')->for($secondUnit, 'unit')->create();

    $this->actingAs($ctx['user'])
        ->put("/settings/units/{$secondUnit->id}/headquarters")
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade definida como matriz.']);

    expect($secondUnit->fresh()->is_headquarters)->toBeTrue()
        ->and($ctx['headquarters']->fresh()->is_headquarters)->toBeFalse();
});

it('flashes a toast confirmation after creating a unit', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post('/settings/units', [
        'name' => 'Unidade Oeste',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [],
    ])
        ->assertRedirect('/settings/units')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade criada com sucesso.']);
});

it('flashes a toast confirmation after updating a unit', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put("/settings/units/{$ctx['headquarters']->id}", [
            'name' => 'Matriz Renomeada',
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'timezone' => $ctx['headquarters']->timezone,
            'address' => validUnitAddressPayload(),
        ])
        ->assertRedirect('/settings/units')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade atualizada com sucesso.']);
});

it('updates the address of an existing unit', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put("/settings/units/{$ctx['headquarters']->id}", [
            'name' => $ctx['headquarters']->name,
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'timezone' => $ctx['headquarters']->timezone,
            'address' => [
                'postal_code' => '20040020',
                'street' => 'Avenida Rio Branco',
                'number' => '100',
                'neighborhood' => 'Centro',
                'city' => 'Rio de Janeiro',
                'state' => 'RJ',
            ],
        ])
        ->assertRedirect('/settings/units');

    $ctx['headquarters']->refresh();
    expect($ctx['headquarters']->address->street)->toBe('Avenida Rio Branco')
        ->and($ctx['headquarters']->address->city)->toBe('Rio de Janeiro')
        ->and($ctx['headquarters']->address->state)->toBe('RJ');
});

it('updates the opening hours of an existing unit', function () {
    $ctx = ownerActingInOrganization();
    $ctx['headquarters']->openingHours()->create([
        'organization_id' => $ctx['organization']->id,
        'day_of_week' => 1,
        'opens_at' => '08:00',
        'closes_at' => '12:00',
        'sort_order' => 0,
    ]);

    $this->actingAs($ctx['user'])
        ->put("/settings/units/{$ctx['headquarters']->id}", [
            'name' => $ctx['headquarters']->name,
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'timezone' => $ctx['headquarters']->timezone,
            'address' => validUnitAddressPayload(),
            'opening_hours' => [
                ['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '19:00'],
            ],
        ])
        ->assertRedirect('/settings/units');

    $ctx['headquarters']->refresh();
    expect($ctx['headquarters']->openingHours()->count())->toBe(1)
        ->and($ctx['headquarters']->openingHours()->first())
        ->day_of_week->toBe(2)
        ->opens_at->toBe('09:00:00')
        ->closes_at->toBe('19:00:00');
});

it('leaves existing opening hours untouched when the update request omits the field', function () {
    $ctx = ownerActingInOrganization();
    $ctx['headquarters']->openingHours()->create([
        'organization_id' => $ctx['organization']->id,
        'day_of_week' => 1,
        'opens_at' => '08:00',
        'closes_at' => '12:00',
        'sort_order' => 0,
    ]);

    $this->actingAs($ctx['user'])
        ->put("/settings/units/{$ctx['headquarters']->id}", [
            'name' => $ctx['headquarters']->name,
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'timezone' => $ctx['headquarters']->timezone,
            'address' => validUnitAddressPayload(),
        ])
        ->assertRedirect('/settings/units');

    expect($ctx['headquarters']->openingHours()->count())->toBe(1);
});

it('rejects an update with overlapping opening hours intervals on the same day', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put("/settings/units/{$ctx['headquarters']->id}", [
            'name' => $ctx['headquarters']->name,
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'timezone' => $ctx['headquarters']->timezone,
            'address' => validUnitAddressPayload(),
            'opening_hours' => [
                ['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00'],
                ['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '18:00'],
            ],
        ])
        ->assertSessionHasErrors('opening_hours');
});

it('flashes distinct toast confirmations when activating and inactivating a unit', function () {
    $ctx = ownerActingInOrganization();
    $secondUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create(['status' => RecordStatus::Active]);
    UnitMembership::factory()->for($ctx['membership'], 'organizationMembership')->for($secondUnit, 'unit')->create();

    $this->actingAs($ctx['user'])
        ->patch("/settings/units/{$secondUnit->id}/status", ['active' => false])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade inativada com sucesso.']);

    $this->actingAs($ctx['user'])
        ->patch("/settings/units/{$secondUnit->id}/status", ['active' => true])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade ativada com sucesso.']);
});

it('flashes a toast confirmation after deleting and restoring a unit', function () {
    $ctx = ownerActingInOrganization();
    $secondUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create();
    UnitMembership::factory()->for($ctx['membership'], 'organizationMembership')->for($secondUnit, 'unit')->create();

    $this->actingAs($ctx['user'])
        ->delete("/settings/units/{$secondUnit->id}")
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade excluída com sucesso. Seu histórico foi preservado.']);

    $this->actingAs($ctx['user'])
        ->post("/settings/units/{$secondUnit->id}/restore")
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade restaurada com sucesso.']);
});

it('allows reactivating a unit that is currently inactive', function () {
    $ctx = ownerActingInOrganization();
    $secondUnit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create(['status' => RecordStatus::Inactive]);

    // Sem UnitMembership nesta unidade — só o vínculo de owner da
    // organização é exigido pela Policy para a rota de status.
    $this->actingAs($ctx['user'])
        ->patch("/settings/units/{$secondUnit->id}/status", ['active' => true])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidade ativada com sucesso.']);

    expect($secondUnit->fresh()->status)->toBe(RecordStatus::Active);
});

it('does not allow toggling the status of a unit belonging to another organization', function () {
    $ctx = ownerActingInOrganization();

    $otherOrganization = Organization::factory()->create();
    $otherLegalEntity = LegalEntity::factory()->primary()->for($otherOrganization)->create();
    $foreignUnit = Unit::factory()->for($otherOrganization)->for($otherLegalEntity, 'legalEntity')->create(['status' => RecordStatus::Active]);

    $this->actingAs($ctx['user'])
        ->patch("/settings/units/{$foreignUnit->id}/status", ['active' => false])
        ->assertNotFound();

    expect($foreignUnit->fresh()->status)->toBe(RecordStatus::Active);
});

it('eager-loads the address and opening hours so the listing can show and inline-edit a unit', function () {
    $ctx = ownerActingInOrganization();

    $response = $this->actingAs($ctx['user'])->get('/settings/units');

    $response->assertInertia(fn ($page) => $page
        ->has('units.0.address')
        ->has('units.0.opening_hours')
    );
});
