<?php

declare(strict_types=1);

use App\Actions\Organization\ActivateOrganizationMembershipAction;
use App\Actions\Organization\DeactivateOrganizationMembershipAction;
use App\Actions\Organization\GrantUnitAccessAction;
use App\Actions\Organization\RestoreUnitAccessAction;
use App\Actions\Organization\RevokeUnitAccessAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function organizationWithTwoOwners(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $ownerA = OrganizationMembership::factory()->owner()->for($organization)->for(User::factory())->create();
    $ownerB = OrganizationMembership::factory()->owner()->for($organization)->for(User::factory())->create();

    UnitMembership::factory()->for($ownerA, 'organizationMembership')->for($headquarters, 'unit')->create();
    UnitMembership::factory()->for($ownerB, 'organizationMembership')->for($headquarters, 'unit')->create();

    return compact('organization', 'headquarters', 'ownerA', 'ownerB');
}

it('activates an inactive organization membership', function () {
    $membership = OrganizationMembership::factory()->create(['status' => OrganizationMembershipStatus::Inactive]);

    app(ActivateOrganizationMembershipAction::class)->handle($membership);

    expect($membership->fresh()->status)->toBe(OrganizationMembershipStatus::Active);
});

it('deactivates an organization membership when another active owner exists', function () {
    $ctx = organizationWithTwoOwners();

    app(DeactivateOrganizationMembershipAction::class)->handle($ctx['ownerA']);

    expect($ctx['ownerA']->fresh()->status)->toBe(OrganizationMembershipStatus::Inactive);
});

it('refuses to deactivate the last active owner', function () {
    $organization = Organization::factory()->create();
    $soleOwner = OrganizationMembership::factory()->owner()->for($organization)->for(User::factory())->create();

    expect(fn () => app(DeactivateOrganizationMembershipAction::class)->handle($soleOwner))
        ->toThrow(ValidationException::class);

    expect($soleOwner->fresh()->status)->toBe(OrganizationMembershipStatus::Active);
});

it('grants unit access to an organization member', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $membership = OrganizationMembership::factory()->for($organization)->for(User::factory())->create();

    $unitMembership = app(GrantUnitAccessAction::class)->handle($membership, $unit);

    expect($unitMembership->status)->toBe(RecordStatus::Active)
        ->and($unitMembership->unit_id)->toBe($unit->id);
});

it('refuses to revoke the last active owner access to the headquarters unit', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $soleOwner = OrganizationMembership::factory()->owner()->for($organization)->for(User::factory())->create();
    $unitMembership = UnitMembership::factory()->for($soleOwner, 'organizationMembership')->for($headquarters, 'unit')->create();

    expect(fn () => app(RevokeUnitAccessAction::class)->handle($unitMembership))
        ->toThrow(ValidationException::class);

    expect($unitMembership->fresh()->status)->toBe(RecordStatus::Active);
});

it('allows revoking headquarters access when another active owner remains', function () {
    $ctx = organizationWithTwoOwners();
    $unitMembership = UnitMembership::query()
        ->where('organization_membership_id', $ctx['ownerA']->id)
        ->where('unit_id', $ctx['headquarters']->id)
        ->firstOrFail();

    app(RevokeUnitAccessAction::class)->handle($unitMembership);

    expect($unitMembership->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('restores a revoked unit access', function () {
    $unitMembership = UnitMembership::factory()->create(['status' => RecordStatus::Inactive]);

    app(RestoreUnitAccessAction::class)->handle($unitMembership);

    expect($unitMembership->fresh()->status)->toBe(RecordStatus::Active);
});
