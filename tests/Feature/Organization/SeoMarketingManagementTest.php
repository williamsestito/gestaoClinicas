<?php

declare(strict_types=1);

use App\Models\OrganizationMembership;
use App\Models\SiteSetting;
use App\Models\User;

function validSeoMarketingPayload(array $overrides = []): array
{
    return array_merge([
        'schema_type' => 'LocalBusiness',
        'indexing_policy' => 'noindex',
        'integration_method' => 'none',
    ], $overrides);
}

it('lets the owner update SEO and marketing configuration', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.seo.update'), validSeoMarketingPayload([
            'official_domain' => 'clinicaexemplo.com.br',
            'meta_title' => 'Clínica Exemplo',
            'integration_method' => 'gtag',
            'ga4_measurement_id' => 'G-ABC1234567',
            'ga4_enabled' => true,
        ]))
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $site = SiteSetting::query()->first();

    expect($site->official_domain)->toBe('clinicaexemplo.com.br')
        ->and($site->ga4_measurement_id)->toBe('G-ABC1234567')
        ->and($site->ga4_enabled)->toBeTrue();
});

it('rejects a malformed GA4 measurement id', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.seo.update'), validSeoMarketingPayload([
            'ga4_measurement_id' => 'not-a-valid-id',
        ]))
        ->assertSessionHasErrors('ga4_measurement_id');
});

it('rejects a malformed GTM container id', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.seo.update'), validSeoMarketingPayload([
            'gtm_container_id' => 'wrong-format',
        ]))
        ->assertSessionHasErrors('gtm_container_id');
});

it('rejects a malformed Google Ads conversion id', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.seo.update'), validSeoMarketingPayload([
            'google_ads_conversion_id' => 'invalid',
        ]))
        ->assertSessionHasErrors('google_ads_conversion_id');
});

it('rejects an official domain with a protocol or path', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.seo.update'), validSeoMarketingPayload([
            'official_domain' => 'https://clinicaexemplo.com.br/pagina',
        ]))
        ->assertSessionHasErrors('official_domain');
});

it('blocks a non-owner without seo.update permission', function () {
    $ctx = ownerActingInOrganization();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($member)
        ->put(route('settings.seo.update'), validSeoMarketingPayload())
        ->assertForbidden();
});
