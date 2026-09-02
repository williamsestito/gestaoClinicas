<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\SiteGalleryItem;
use App\Models\SiteSetting;

it('shows no items when the site is not published yet', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['is_published' => false]);
    SiteGalleryItem::factory()->create(['is_active' => true]);

    $this->get('/galeria')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Gallery')
            ->where('items', null)
        );
});

it('lists only active gallery items, with captions, once the site is published', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['is_published' => true, 'title' => 'Clínica Essenza']);
    SiteGalleryItem::factory()->create(['is_active' => true, 'caption' => 'Recepção da clínica']);
    SiteGalleryItem::factory()->create(['is_active' => false, 'caption' => 'Inativa']);

    $this->get('/galeria')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Gallery')
            ->where('siteTitle', 'Clínica Essenza')
            ->has('items.data', 1)
            ->where('items.data.0.caption', 'Recepção da clínica')
        );
});

it('orders gallery items by the configured order, then paginates', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['is_published' => true]);

    SiteGalleryItem::factory()->create(['is_active' => true, 'order' => 1, 'caption' => 'Segunda']);
    SiteGalleryItem::factory()->create(['is_active' => true, 'order' => 0, 'caption' => 'Primeira']);

    $this->get('/galeria')
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.caption', 'Primeira')
            ->where('items.data.1.caption', 'Segunda')
        );
});

it('paginates the gallery instead of returning every item at once', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['is_published' => true]);
    SiteGalleryItem::factory()->count(30)->create(['is_active' => true]);

    $this->get('/galeria')
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 24)
            ->where('items.last_page', 2)
        );
});

it('sets a canonical URL and defaults to noindex outside of production', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create(['is_published' => true]);

    $this->get('/galeria')
        ->assertInertia(fn ($page) => $page
            ->where('seo.robots', 'noindex, nofollow')
            ->where('seo.canonical', url('/galeria'))
        );
});
