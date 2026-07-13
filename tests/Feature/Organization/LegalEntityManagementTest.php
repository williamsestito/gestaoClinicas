<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

function ownerActingInOrganizationWithLegalEntity(): array
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

it('creates a new legal entity for the active organization', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();

    $this->actingAs($ctx['user'])->post('/settings/legal-entities', [
        'type' => 'company',
        'document' => '11222333000181',
        'legal_name' => 'Clínica Secundária LTDA',
        'trade_name' => 'Clínica Secundária',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua B',
            'number' => '20',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
    ])->assertRedirect('/settings/legal-entities');

    expect(LegalEntity::query()->where('organization_id', $ctx['organization']->id)->where('document', '11222333000181')->exists())->toBeTrue();
});

it('refuses to inactivate the primary legal entity', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();

    $this->actingAs($ctx['user'])
        ->patch("/settings/legal-entities/{$ctx['legalEntity']->id}/status", ['active' => false])
        ->assertSessionHasErrors('legal_entity');

    expect($ctx['legalEntity']->fresh()->status)->toBe(RecordStatus::Active);
});

it('refuses to delete the primary legal entity', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();

    $this->actingAs($ctx['user'])
        ->delete("/settings/legal-entities/{$ctx['legalEntity']->id}")
        ->assertSessionHasErrors('legal_entity');

    expect($ctx['legalEntity']->fresh()->trashed())->toBeFalse();
});

it('logically deletes a non-primary legal entity and preserves its history', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();
    $secondary = LegalEntity::factory()->for($ctx['organization'])->create();

    $this->actingAs($ctx['user'])
        ->delete("/settings/legal-entities/{$secondary->id}")
        ->assertRedirect();

    expect($secondary->fresh()->trashed())->toBeTrue()
        ->and(LegalEntity::query()->find($secondary->id))->toBeNull()
        ->and(LegalEntity::withTrashed()->find($secondary->id))->not->toBeNull();
});

it('restores a logically deleted legal entity', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();
    $secondary = LegalEntity::factory()->for($ctx['organization'])->create();
    $secondary->delete();

    $this->actingAs($ctx['user'])
        ->post("/settings/legal-entities/{$secondary->id}/restore")
        ->assertRedirect();

    expect($secondary->fresh()->trashed())->toBeFalse();
});

it('atomically swaps the primary legal entity', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();
    $secondary = LegalEntity::factory()->for($ctx['organization'])->create();

    $this->actingAs($ctx['user'])
        ->put("/settings/legal-entities/{$secondary->id}/primary")
        ->assertRedirect();

    expect($secondary->fresh()->is_primary)->toBeTrue()
        ->and($ctx['legalEntity']->fresh()->is_primary)->toBeFalse();
});

it('blocks access to a legal entity belonging to another organization even with a tampered URL', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();

    $otherOrganization = Organization::factory()->create();
    $foreignEntity = LegalEntity::factory()->for($otherOrganization)->create();

    $this->actingAs($ctx['user'])
        ->get("/settings/legal-entities/{$foreignEntity->id}/edit")
        ->assertNotFound();
});

it('normalizes a document submitted with punctuation before storing it', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();

    $this->actingAs($ctx['user'])->post('/settings/legal-entities', [
        'type' => 'company',
        'document' => '11.222.333/0001-81',
        'legal_name' => 'Clínica Terciária LTDA',
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Rua B',
            'number' => '20',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
    ])->assertRedirect('/settings/legal-entities');

    $entity = LegalEntity::query()->where('legal_name', 'Clínica Terciária LTDA')->firstOrFail();
    expect($entity->document)->toBe('11222333000181');
});

it('never sends the unmasked document in the index or edit inertia props', function () {
    $ctx = ownerActingInOrganizationWithLegalEntity();
    $rawDocumentPrefix = substr($ctx['legalEntity']->document, 0, 5);

    $indexResponse = $this->actingAs($ctx['user'])->get('/settings/legal-entities');
    $indexResponse->assertInertia(fn ($page) => $page
        ->where('legalEntities.0.document', fn ($document) => ! str_contains((string) $document, $rawDocumentPrefix)));

    $editResponse = $this->actingAs($ctx['user'])->get("/settings/legal-entities/{$ctx['legalEntity']->id}/edit");
    $editResponse->assertInertia(fn ($page) => $page
        ->where('legalEntity.document', fn ($document) => ! str_contains((string) $document, $rawDocumentPrefix)));
});
