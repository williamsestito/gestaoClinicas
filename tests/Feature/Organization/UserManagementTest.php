<?php

declare(strict_types=1);

use App\Enums\InvitationStatus;
use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

it('lets the owner invite a user and sends the invitation notification', function () {
    Notification::fake();
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $this->actingAs($ctx['user'])
        ->post(route('settings.users.invite'), [
            'email' => 'nova@example.com',
            'role_id' => $role->id,
            'unit_ids' => [$ctx['headquarters']->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $invitation = Invitation::query()->where('email', 'nova@example.com')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->status)->toBe(InvitationStatus::Pending)
        ->and($invitation->role_id)->toBe($role->id)
        ->and($invitation->units()->pluck('units.id')->all())->toBe([$ctx['headquarters']->id]);

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

it('never lets an invited user become a platform admin on acceptance', function () {
    $organization = Organization::factory()->create();
    $inviter = User::factory()->create();
    $invitation = Invitation::factory()->for($organization)->create(['invited_by' => $inviter->id]);

    $token = 'known-raw-token-for-test';
    $invitation->update(['token_hash' => hash('sha256', $token)]);

    $response = $this->post(route('invitations.store', $token), [
        'name' => 'Nova Pessoa',
        'password' => 'senha12345',
        'password_confirmation' => 'senha12345',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', $invitation->email)->firstOrFail();

    expect($user->is_platform_admin)->toBeFalse()
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);

    $membership = OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organization->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->is_owner)->toBeFalse();
});

it('rejects accepting an expired invitation', function () {
    $invitation = Invitation::factory()->expired()->create();
    $token = 'expired-token';
    $invitation->update(['token_hash' => hash('sha256', $token)]);

    $this->post(route('invitations.store', $token), [
        'name' => 'Tentando Tarde',
        'password' => 'senha12345',
        'password_confirmation' => 'senha12345',
    ])->assertSessionHasErrors('invitation');

    expect(User::query()->where('email', $invitation->email)->exists())->toBeFalse();
});

it('lets the owner cancel a pending invitation', function () {
    $ctx = ownerActingInOrganization();
    $invitation = Invitation::factory()->for($ctx['organization'])->create(['invited_by' => $ctx['user']->id]);

    $this->actingAs($ctx['user'])
        ->post(route('settings.invitations.cancel', $invitation))
        ->assertSessionHasNoErrors();

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Cancelled);
});

it('lets the owner resend an invitation with a fresh token', function () {
    Notification::fake();
    $ctx = ownerActingInOrganization();
    $invitation = Invitation::factory()->for($ctx['organization'])->create(['invited_by' => $ctx['user']->id]);
    $originalHash = $invitation->token_hash;

    $this->actingAs($ctx['user'])
        ->post(route('settings.invitations.resend', $invitation))
        ->assertSessionHasNoErrors();

    expect($invitation->fresh()->token_hash)->not->toBe($originalHash);
});

it('lets the owner deactivate a non-owner member, blocking their login', function () {
    $ctx = ownerActingInOrganization();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($ctx['user'])
        ->patch(route('settings.users.deactivate', $membership))
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    expect($member->fresh()->is_active)->toBeFalse();
});

it('blocks deactivating the sole active owner', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->patch(route('settings.users.deactivate', $ctx['membership']))
        ->assertSessionHasErrors('user');

    expect($ctx['user']->fresh()->is_active)->toBeTrue();
});

it('lets the owner assign a role and units to a member in one update', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::UnitManager->value)->firstOrFail();

    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $unit = Unit::factory()->for($ctx['organization'])->for($ctx['legalEntity'], 'legalEntity')->create();

    $this->actingAs($ctx['user'])
        ->put(route('settings.users.update', $membership), [
            'role_id' => $role->id,
            'unit_ids' => [$unit->id],
            'primary_unit_id' => $unit->id,
        ])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->role_id)->toBe($role->id);
    $unitMembership = $membership->unitMemberships()->where('unit_id', $unit->id)->first();
    expect($unitMembership)->not->toBeNull()
        ->and($unitMembership->is_primary)->toBeTrue();
});

it('records the last login timestamp on successful authentication', function () {
    $user = User::factory()->create(['password' => bcrypt('senha12345')]);

    expect($user->last_login_at)->toBeNull();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha12345',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
