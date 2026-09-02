<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\Tenancy\OwnershipGuard;

it('reports true for the only active owner membership in the organization', function () {
    $organization = Organization::factory()->create();
    $owner = OrganizationMembership::factory()->owner()->for($organization)->create();

    expect(OwnershipGuard::isSoleActiveOwner($owner))->toBeTrue();
});

it('reports false when another active owner membership exists', function () {
    $organization = Organization::factory()->create();
    $owner = OrganizationMembership::factory()->owner()->for($organization)->create();
    OrganizationMembership::factory()->owner()->for($organization)->create();

    expect(OwnershipGuard::isSoleActiveOwner($owner))->toBeFalse();
});

it('reports true when the other owner membership belongs to a different organization', function () {
    $organization = Organization::factory()->create();
    $owner = OrganizationMembership::factory()->owner()->for($organization)->create();
    OrganizationMembership::factory()->owner()->create();

    expect(OwnershipGuard::isSoleActiveOwner($owner))->toBeTrue();
});

it('reports false for a membership that is not an owner', function () {
    $organization = Organization::factory()->create();
    $member = OrganizationMembership::factory()->for($organization)->create();

    expect(OwnershipGuard::isSoleActiveOwner($member))->toBeFalse();
});

it('reports false when the owner membership itself is inactive', function () {
    $organization = Organization::factory()->create();
    $owner = OrganizationMembership::factory()->owner()->for($organization)->create([
        'status' => OrganizationMembershipStatus::Inactive,
    ]);

    expect(OwnershipGuard::isSoleActiveOwner($owner))->toBeFalse();
});

it('ignores an inactive second owner membership and still reports sole active owner', function () {
    $organization = Organization::factory()->create();
    $owner = OrganizationMembership::factory()->owner()->for($organization)->create();
    OrganizationMembership::factory()->owner()->for($organization)->create([
        'status' => OrganizationMembershipStatus::Inactive,
    ]);

    expect(OwnershipGuard::isSoleActiveOwner($owner))->toBeTrue();
});
