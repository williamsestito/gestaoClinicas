<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Queries\CurrentInstallationOrganizationQuery;
use Illuminate\Support\Facades\Log;

it('returns null when no organization exists yet', function () {
    expect(app(CurrentInstallationOrganizationQuery::class)->resolve())->toBeNull();
});

it('returns the only organization when exactly one exists', function () {
    $organization = Organization::factory()->create();

    expect(app(CurrentInstallationOrganizationQuery::class)->resolve()->id)->toBe($organization->id);
});

it('deterministically resolves the oldest organization when more than one exists, instead of an unordered pick', function () {
    Log::shouldReceive('warning')->times(3);

    $older = Organization::factory()->create(['created_at' => now()->subDay()]);
    Organization::factory()->create(['created_at' => now()]);

    $query = app(CurrentInstallationOrganizationQuery::class);

    // Chamado várias vezes para simular requisições públicas distintas —
    // deve sempre devolver a mesma organização (a mais antiga), nunca uma
    // escolha aleatória do banco.
    expect($query->resolve()->id)->toBe($older->id)
        ->and($query->resolve()->id)->toBe($older->id)
        ->and($query->resolve()->id)->toBe($older->id);
});

it('logs a warning when more than one organization exists — an installation should never reach this state (ADR-010)', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with(
            Mockery::pattern('/single-tenant/'),
            Mockery::on(fn (array $context) => count($context['organization_ids']) === 2),
        );

    Organization::factory()->count(2)->create();

    app(CurrentInstallationOrganizationQuery::class)->resolve();
});

it('never logs a warning when the installation has zero or one organization, the expected states', function () {
    Log::shouldReceive('warning')->never();

    app(CurrentInstallationOrganizationQuery::class)->resolve();

    Organization::factory()->create();

    app(CurrentInstallationOrganizationQuery::class)->resolve();
});
