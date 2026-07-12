<?php

declare(strict_types=1);

use App\Enums\OrganizationStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

/** @return array{organization: Organization, unit: Unit, user: User, membership: OrganizationMembership} */
function createOrganizationWithOwner(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unit, 'unit')->create();

    return compact('organization', 'unit', 'user', 'membership');
}

it('does not let a user without any membership reach the clinic area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.organization.create'));
});

it('lets an owner access their own organization dashboard', function () {
    $ctx = createOrganizationWithOwner();

    $this->actingAs($ctx['user'])->get('/dashboard')->assertOk();
});

it('never resolves another organization context even if the id is guessed', function () {
    $ctxA = createOrganizationWithOwner();
    $ctxB = createOrganizationWithOwner();

    // Usuário da organização A tenta forjar o contexto ativo com o ID da
    // organização B, à qual não tem vínculo — a sessão deve ser ignorada e
    // o middleware deve manter a resolução real do usuário (organização A),
    // nunca a organização B.
    $this->actingAs($ctxA['user']);
    session(['active_organization_id' => $ctxB['organization']->id]);

    $response = $this->get('/dashboard');

    $response->assertOk();
    expect(session('active_organization_id'))->toBe($ctxA['organization']->id);
});

it('returns 404 when trying to edit a unit that belongs to another organization', function () {
    $ctxA = createOrganizationWithOwner();
    $ctxB = createOrganizationWithOwner();

    session(['active_organization_id' => $ctxA['organization']->id, 'active_unit_id' => $ctxA['unit']->id]);

    $this->actingAs($ctxA['user'])
        ->get("/settings/units/{$ctxB['unit']->id}/edit")
        ->assertNotFound();
});

it('does not allow selecting a unit without an active unit membership', function () {
    $ctx = createOrganizationWithOwner();
    $otherUnit = Unit::factory()->for($ctx['organization'])->for($ctx['unit']->legalEntity, 'legalEntity')->create();

    session(['active_organization_id' => $ctx['organization']->id]);

    $this->actingAs($ctx['user'])
        ->put('/context/unit', ['unit_id' => $otherUnit->id])
        ->assertSessionHasErrors('unit');
});

it('blocks access to a suspended organization', function () {
    $ctx = createOrganizationWithOwner();
    $ctx['organization']->update(['status' => OrganizationStatus::Suspended]);

    session(['active_organization_id' => $ctx['organization']->id]);

    $this->actingAs($ctx['user'])->get('/dashboard')->assertForbidden();
});

it('does not allow a regular user to access another user platform admin panel', function () {
    $regular = User::factory()->create();

    $this->actingAs($regular)->get('/admin/organizations')->assertForbidden();
});
