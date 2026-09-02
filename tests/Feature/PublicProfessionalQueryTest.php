<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\SiteProfessional;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\Unit;
use App\Queries\PublicProfessionalQuery;

function eligibleOperationalProfessional(Organization $organization, array $overrides = []): Professional
{
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $specialty = Specialty::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create(array_merge([
        'status' => RecordStatus::Active,
        'is_public' => true,
    ], $overrides));
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id, 'status' => RecordStatus::Active]);
    ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id, 'status' => RecordStatus::Active]);

    return $professional;
}

it('shows a public operational professional without a promotional record', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->pluck('professional_id'))->toContain($professional->id);
});

it('never shows a non-public operational professional', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization, ['is_public' => false]);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->pluck('professional_id'))->not->toContain($professional->id);
});

it('never shows an inactive operational professional', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization, ['status' => RecordStatus::Inactive]);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->pluck('professional_id'))->not->toContain($professional->id);
});

it('never shows a deleted operational professional', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization);
    $professional->delete();

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->pluck('professional_id'))->not->toContain($professional->id);
});

it('never shows a public professional from another organization', function () {
    $organization = Organization::factory()->create();
    $foreignProfessional = eligibleOperationalProfessional(Organization::factory()->create());

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->pluck('professional_id'))->not->toContain($foreignProfessional->id);
});

it('never shows a public professional without an active unit or without specialty/service', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active, 'is_public' => true]);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->pluck('professional_id'))->not->toContain($professional->id);
});

it('shows an independent SiteProfessional without any operational link', function () {
    $organization = Organization::factory()->create();
    SiteProfessional::factory()->create(['is_active' => true, 'professional_id' => null]);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->count())->toBe(1);
});

it('shows a linked professional exactly once, prioritizing promotional data', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization, ['display_name' => 'Nome Operacional', 'bio' => 'Bio operacional']);
    SiteProfessional::factory()->create([
        'is_active' => true,
        'professional_id' => $professional->id,
        'name' => 'Nome Promocional',
        'bio' => 'Bio promocional',
    ]);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);
    $matching = $result->where('professional_id', $professional->id);

    expect($matching)->toHaveCount(1)
        ->and($matching->first()['name'])->toBe('Nome Promocional')
        ->and($matching->first()['bio'])->toBe('Bio promocional');
});

it('never deduplicates by name alone, only by explicit link', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization, ['display_name' => 'Dra. Ana Souza']);
    SiteProfessional::factory()->create(['is_active' => true, 'professional_id' => null, 'name' => 'Dra. Ana Souza']);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);

    expect($result->count())->toBe(2);
});

it('never exposes sensitive operational data in the public payload', function () {
    $organization = Organization::factory()->create();
    $professional = eligibleOperationalProfessional($organization, [
        'email' => 'privado@example.com',
        'phone' => '11999999999',
        'document' => '52998224725',
    ]);

    $result = app(PublicProfessionalQuery::class)->forOrganization($organization);
    $row = $result->firstWhere('professional_id', $professional->id);

    expect($row)->not->toHaveKeys(['email', 'phone', 'document', 'user_id', 'organization_id']);
});

it('uses the same normalized source in the public Equipe section', function () {
    $organization = Organization::factory()->create();
    SiteSetting::factory()->create();
    $professional = eligibleOperationalProfessional($organization);

    $response = $this->get('/');

    $names = collect($response->viewData('page')['props']['professionals'])->pluck('professional_id');
    expect($names)->toContain($professional->id);
});
