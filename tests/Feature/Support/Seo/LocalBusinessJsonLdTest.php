<?php

declare(strict_types=1);

use App\Enums\SchemaBusinessType;
use App\Models\Address;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\Unit;
use App\Models\UnitOpeningHour;
use App\Support\Seo\LocalBusinessJsonLd;

function buildLocalBusinessJsonLd(Organization $organization, ?SiteSetting $siteSetting = null): ?array
{
    return app(LocalBusinessJsonLd::class)->build($organization, $siteSetting);
}

it('returns null when the organization has no headquarters address or phone', function () {
    $organization = Organization::factory()->create();

    expect(buildLocalBusinessJsonLd($organization))->toBeNull();
});

it('builds a valid LocalBusiness JSON-LD from real data only', function () {
    $organization = Organization::factory()->create(['name' => 'Clínica Exemplo']);
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create(['trade_name' => 'Exemplo Estética']);
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create([
        'phone' => '4732221122',
        'email' => 'contato@exemplo.com.br',
    ]);
    Address::factory()->for($organization)->for($unit, 'addressable')->create([
        'street' => 'Rua das Flores',
        'number' => '100',
        'city' => 'Joinville',
        'state' => 'SC',
        'postal_code' => '89201000',
    ]);
    UnitOpeningHour::factory()->for($organization)->for($unit)->create([
        'day_of_week' => 1,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
    ]);

    $jsonLd = buildLocalBusinessJsonLd($organization);

    expect($jsonLd)->not->toBeNull()
        ->and($jsonLd['@context'])->toBe('https://schema.org')
        ->and($jsonLd['@type'])->toBe('LocalBusiness')
        ->and($jsonLd['name'])->toBe('Clínica Exemplo')
        ->and($jsonLd['alternateName'])->toBe('Exemplo Estética')
        ->and($jsonLd['telephone'])->toBe('4732221122')
        ->and($jsonLd['email'])->toBe('contato@exemplo.com.br')
        ->and($jsonLd['address'])->toBe([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Rua das Flores, 100',
            'addressLocality' => 'Joinville',
            'addressRegion' => 'SC',
            'postalCode' => '89201000',
            'addressCountry' => 'BR',
        ])
        ->and($jsonLd['openingHoursSpecification'])->toBe([[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'https://schema.org/Monday',
            'opens' => '08:00',
            'closes' => '18:00',
        ]]);

    expect($jsonLd)->not->toHaveKey('geo')
        ->and($jsonLd)->not->toHaveKey('sameAs')
        ->and($jsonLd)->not->toHaveKey('aggregateRating')
        ->and($jsonLd)->not->toHaveKey('review');
});

it('uses the configured schema type instead of the default', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create(['phone' => '4732221122']);
    $siteSetting = SiteSetting::factory()->create(['schema_type' => SchemaBusinessType::MedicalClinic->value]);

    $jsonLd = buildLocalBusinessJsonLd($organization, $siteSetting);

    expect($jsonLd['@type'])->toBe('MedicalClinic');
});

it('only includes geo coordinates when both latitude and longitude are configured', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create(['phone' => '4732221122']);
    $siteSetting = SiteSetting::factory()->create(['latitude' => -26.3044, 'longitude' => -48.8464]);

    $jsonLd = buildLocalBusinessJsonLd($organization, $siteSetting);

    expect($jsonLd['geo'])->toBe([
        '@type' => 'GeoCoordinates',
        'latitude' => -26.3044,
        'longitude' => -48.8464,
    ]);
});

it('never invents sameAs links — only includes URLs the administrator configured', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create(['phone' => '4732221122']);
    $siteSetting = SiteSetting::factory()->create(['google_business_profile_url' => 'https://business.google.com/exemplo']);

    $jsonLd = buildLocalBusinessJsonLd($organization, $siteSetting);

    expect($jsonLd['sameAs'])->toBe(['https://business.google.com/exemplo']);
});
