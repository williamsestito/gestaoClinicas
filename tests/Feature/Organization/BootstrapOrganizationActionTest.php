<?php

declare(strict_types=1);

use App\Actions\Organization\BootstrapOrganizationAction;
use App\Data\Organization\BootstrapOrganizationData;
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

/** @return array<string, mixed> */
function bootstrapOrganizationPayload(array $overrides = []): array
{
    return array_replace([
        'organization_name' => 'Clínica Aurora',
        'legal_entity_type' => 'company',
        'document' => '11222333000181',
        'legal_name' => 'Clínica Aurora Ltda.',
        'trade_name' => 'Clínica Aurora',
        'unit_name' => 'Matriz',
        'unit_phone' => '4732221100',
        'unit_whatsapp' => null,
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'complement' => null,
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'sp',
        ],
        'existing_owner_user_id' => null,
        'invite_name' => null,
        'invite_email' => null,
    ], $overrides);
}

it('creates the organization, legal entity, headquarters and system roles, and invites the chosen administrator', function () {
    Notification::fake();
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

    $organization = app(BootstrapOrganizationAction::class)->handle(
        $platformAdmin,
        BootstrapOrganizationData::fromArray(bootstrapOrganizationPayload([
            'invite_name' => 'Ana Souza',
            'invite_email' => 'ana@example.com',
        ])),
    );

    expect($organization->name)->toBe('Clínica Aurora')
        ->and($organization->slug)->not->toBeEmpty();

    $legalEntity = LegalEntity::query()->where('organization_id', $organization->id)->firstOrFail();
    expect($legalEntity->document)->toBe('11222333000181')
        ->and($legalEntity->is_primary)->toBeTrue();

    $unit = Unit::query()->where('organization_id', $organization->id)->firstOrFail();
    expect($unit->is_headquarters)->toBeTrue()
        ->and($unit->address)->not->toBeNull();

    expect(Role::query()->where('organization_id', $organization->id)->count())->toBe(7);

    // Nenhum vínculo/usuário criado ainda: o administrador convidado só
    // existe quando aceita o convite (AcceptInvitationAction).
    expect(OrganizationMembership::query()->where('organization_id', $organization->id)->count())->toBe(0)
        ->and(User::query()->where('email', 'ana@example.com')->exists())->toBeFalse();

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

it('links an existing user as owner immediately, with no invitation, when one is selected instead of inviting by e-mail', function () {
    Notification::fake();
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $existingUser = User::factory()->create();

    $organization = app(BootstrapOrganizationAction::class)->handle(
        $platformAdmin,
        BootstrapOrganizationData::fromArray(bootstrapOrganizationPayload([
            'existing_owner_user_id' => $existingUser->id,
        ])),
    );

    $membership = OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $existingUser->id)
        ->firstOrFail();

    expect($membership->is_owner)->toBeTrue()
        ->and($membership->role->slug)->toBe(SystemRole::Owner->value);

    $unit = Unit::query()->where('organization_id', $organization->id)->firstOrFail();
    expect(UnitMembership::query()->where('organization_membership_id', $membership->id)->where('unit_id', $unit->id)->exists())->toBeTrue();

    $existingUser->refresh();
    expect($existingUser->is_platform_admin)->toBeFalse();

    Notification::assertNothingSent();
});

it('rolls back everything when the legal document is already used by another organization', function () {
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $otherOrganization = Organization::factory()->create();
    LegalEntity::factory()->for($otherOrganization)->create(['document' => '11222333000181']);

    expect(fn () => app(BootstrapOrganizationAction::class)->handle(
        $platformAdmin,
        BootstrapOrganizationData::fromArray(bootstrapOrganizationPayload([
            'existing_owner_user_id' => User::factory()->create()->id,
        ])),
    ))->toThrow(Exception::class);

    // Rollback integral: nem a organização nem nada dependente dela ficou
    // gravado — só a organização/entidade legal pré-existente sobrevive.
    expect(Organization::query()->count())->toBe(1)
        ->and(Organization::query()->first()->id)->toBe($otherOrganization->id);
});
