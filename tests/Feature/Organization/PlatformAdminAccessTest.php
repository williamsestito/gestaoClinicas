<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\User;

it('routes a platform admin with no membership anywhere to the organization selector, never to onboarding', function () {
    Organization::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertRedirect(route('context.organization.edit'));
});

it('sends a platform admin to the Filament panel to bootstrap the first organization when none exists yet', function () {
    // Cenário real de produção recém-migrada: users=1 (o platform admin),
    // organizations=0. Instalação single-tenant (ADR-010) — nunca existe
    // "várias organizações para escolher" vazias, só a criação da única
    // organização desta instalação, feita pelo painel (ver
    // OrganizationsTable::emptyStateActions() / CreateOrganization).
    expect(Organization::query()->count())->toBe(0);

    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertRedirect('/admin');
});

it('also redirects a platform admin who reaches the organization selector directly with zero organizations', function () {
    expect(Organization::query()->count())->toBe(0);

    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get(route('context.organization.edit'))
        ->assertRedirect('/admin');
});

it('lets a platform admin see every active organization, even without a membership', function () {
    $orgA = Organization::factory()->create(['name' => 'Org A']);
    $orgB = Organization::factory()->create(['name' => 'Org B']);
    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get(route('context.organization.edit'))
        ->assertInertia(fn ($page) => $page
            ->has('organizations', 2)
        );

    expect($orgA->exists && $orgB->exists)->toBeTrue();
});

it('grants a platform admin real membership on first access to an organization, then lets them pick a unit', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);

    expect(OrganizationMembership::query()->where('user_id', $admin->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->put(route('context.organization.update'), ['organization_id' => $organization->id])
        ->assertRedirect(route('dashboard'));

    $membership = OrganizationMembership::query()
        ->where('user_id', $admin->id)
        ->where('organization_id', $organization->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(OrganizationMembershipStatus::Active)
        ->and($membership->is_owner)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('context.unit.edit'))
        ->assertInertia(fn ($page) => $page->has('units', 1));

    $this->actingAs($admin)
        ->put(route('context.unit.update'), ['unit_id' => $unit->id])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk();
});

it('still routes a regular user with no membership to onboarding, unaffected by the platform admin change', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('onboarding.organization.create'));
});
