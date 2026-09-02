<?php

declare(strict_types=1);

use App\Enums\IndexingPolicy;
use App\Models\SiteSetting;

afterEach(function () {
    app()['env'] = 'testing';
});

it('blocks all crawling outside production, regardless of the indexing policy', function () {
    SiteSetting::factory()->create(['indexing_policy' => IndexingPolicy::Index->value]);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /', escape: false)
        ->assertDontSee('Sitemap:');
});

it('returns 404 for the sitemap outside production', function () {
    SiteSetting::factory()->create(['indexing_policy' => IndexingPolicy::Index->value]);

    $this->get('/sitemap.xml')->assertNotFound();
});

it('blocks crawling in production when indexing policy is noindex', function () {
    app()['env'] = 'production';
    SiteSetting::factory()->create(['indexing_policy' => IndexingPolicy::NoIndex->value]);

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', escape: false);
});

it('allows crawling of public pages and blocks private routes in production when indexing is enabled', function () {
    app()['env'] = 'production';
    SiteSetting::factory()->create(['indexing_policy' => IndexingPolicy::Index->value]);

    $response = $this->get('/robots.txt')->assertOk();
    $response->assertSee('Allow: /', escape: false);
    $response->assertSee('Disallow: /login', escape: false);
    $response->assertSee('Disallow: /admin', escape: false);
    $response->assertSee('Disallow: /dashboard', escape: false);
    $response->assertSee('Sitemap:', escape: false);
});

it('serves a valid sitemap with only the public home URL when indexing is enabled in production', function () {
    app()['env'] = 'production';
    config(['app.url' => 'http://localhost:8080']);
    SiteSetting::factory()->create(['indexing_policy' => IndexingPolicy::Index->value]);

    $response = $this->get('/sitemap.xml')->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee('<loc>http://localhost:8080/</loc>', escape: false);
    $response->assertDontSee('/login', escape: false);
    $response->assertDontSee('/admin', escape: false);
});
