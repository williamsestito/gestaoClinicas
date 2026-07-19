<?php

declare(strict_types=1);

use App\Enums\IndexingPolicy;
use App\Models\Organization;
use App\Models\SiteSetting;

it('renders the Welcome page in the pending-setup state when no organization exists yet', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('organizationConfigured', false)
            ->where('site', null)
        );
});

it('renders the Welcome page with the administered site content once an organization exists', function () {
    Organization::factory()->create();
    $site = SiteSetting::factory()->create([
        'title' => 'Clínica Exemplo',
        'description' => 'Cuidando de você.',
        'primary_color' => '#0F766E',
        'secondary_color' => '#F59E0B',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('organizationConfigured', true)
            ->where('site.title', $site->title)
            ->where('site.description', $site->description)
            ->where('site.primary_color', '#0F766E')
            ->where('site.secondary_color', '#F59E0B')
        );
});

it('exposes the hero image as a public URL when one is set', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['hero_image_path' => 'site-content/hero.jpg']);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('site.hero_image_url', fn (string $url) => str_contains($url, '/storage/site-content/hero.jpg'))
        );
});

it('exposes SEO metadata that never indexes outside production, regardless of the configured policy', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['indexing_policy' => IndexingPolicy::Index->value]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('seo.robots', 'noindex, nofollow')
            ->has('seo.canonical')
            ->has('seo.title')
        );
});

it('omits the JSON-LD block when no organization is configured', function () {
    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('seo.json_ld', null)
        );
});
