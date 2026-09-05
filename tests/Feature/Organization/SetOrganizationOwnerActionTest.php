<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Actions\Organization\SetOrganizationOwnerAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\SystemRole;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

function orphanOrganization(): Organization
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    app(SeedSystemRolesAction::class)->handle($organization);

    return $organization;
}

it('detects an organization without any active owner', function () {
    $organization = orphanOrganization();

    expect(Organization::query()->withoutActiveOwner()->whereKey($organization->id)->exists())->toBeTrue();

    $owner = User::factory()->create();
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Owner->value)->first();
    $organization->memberships()->create([
        'user_id' => $owner->id,
        'status' => OrganizationMembershipStatus::Active,
        'is_owner' => true,
        'role_id' => $role?->id,
        'joined_at' => now(),
    ]);

    expect(Organization::query()->withoutActiveOwner()->whereKey($organization->id)->exists())->toBeFalse();
});

it('links an existing user as the new owner of an orphaned organization, granting headquarters access too', function () {
    $organization = orphanOrganization();
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $newOwner = User::factory()->create();

    app(SetOrganizationOwnerAction::class)->handle($organization, $platformAdmin, (string) $newOwner->id, null);

    $membership = OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $newOwner->id)
        ->firstOrFail();

    expect($membership->is_owner)->toBeTrue()
        ->and($membership->role->slug)->toBe(SystemRole::Owner->value);

    $headquarters = $organization->headquarters()->firstOrFail();
    $unitMembership = UnitMembership::query()
        ->where('organization_membership_id', $membership->id)
        ->where('unit_id', $headquarters->id)
        ->firstOrFail();

    expect($unitMembership->is_manager)->toBeTrue()
        ->and($unitMembership->is_primary)->toBeTrue();

    expect(Organization::query()->withoutActiveOwner()->whereKey($organization->id)->exists())->toBeFalse();
});

it('invites a new administrator by e-mail for an orphaned organization, without creating a user or defining a password', function () {
    Notification::fake();
    $organization = orphanOrganization();
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

    app(SetOrganizationOwnerAction::class)->handle($organization, $platformAdmin, null, 'nova-admin@example.com');

    expect(User::query()->where('email', 'nova-admin@example.com')->exists())->toBeFalse()
        ->and(OrganizationMembership::query()->where('organization_id', $organization->id)->count())->toBe(0);

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

it('never touches memberships of users who are already part of the organization', function () {
    $organization = orphanOrganization();
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $existingMember = User::factory()->create();
    $existingMembership = $organization->memberships()->create([
        'user_id' => $existingMember->id,
        'status' => OrganizationMembershipStatus::Active,
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $newOwner = User::factory()->create();
    app(SetOrganizationOwnerAction::class)->handle($organization, $platformAdmin, (string) $newOwner->id, null);

    $existingMembership->refresh();
    expect($existingMembership->is_owner)->toBeFalse()
        ->and($existingMembership->status)->toBe(OrganizationMembershipStatus::Active);
});
