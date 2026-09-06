<?php

declare(strict_types=1);

use App\Enums\IndexingPolicy;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\Service;
use App\Models\SiteBenefit;
use App\Models\SiteFaq;
use App\Models\SiteGalleryItem;
use App\Models\SitePartner;
use App\Models\SiteProfessional;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Models\Unit;
use App\Support\Site\LandingSections;

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

it('hides the administered site content while it is not published', function () {
    Organization::factory()->create();
    SiteSetting::factory()->unpublished()->create(['title' => 'Não deveria aparecer']);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('organizationConfigured', true)
            ->where('site', null)
        );
});

it('exposes empty collections and no contact when the site is not published', function () {
    Organization::factory()->create();
    SiteSetting::factory()->unpublished()->create();
    SiteBenefit::factory()->create();
    SiteService::factory()->create();

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('sections', [])
            ->where('benefits', [])
            ->where('services', [])
            ->where('partners', [])
            ->where('statistics', [])
            ->where('contact', null)
        );
});

it('exposes active benefits, services, professionals, gallery, testimonials and faqs once published', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create([
        'phone' => '(47) 3222-1122',
    ]);
    SiteSetting::factory()->create();

    SiteBenefit::factory()->create(['title' => 'Ativo', 'is_active' => true]);
    SiteBenefit::factory()->inactive()->create(['title' => 'Inativo']);
    SiteService::factory()->create(['name' => 'Serviço ativo', 'is_active' => true]);
    SiteProfessional::factory()->create(['name' => 'Profissional ativo', 'is_active' => true]);
    SiteGalleryItem::factory()->create(['caption' => 'Foto ativa', 'is_active' => true]);
    SiteTestimonial::factory()->create(['author_name' => 'Cliente ativo', 'is_active' => true]);
    SiteFaq::factory()->create(['question' => 'Pergunta ativa', 'is_active' => true]);
    SitePartner::factory()->create(['name' => 'Parceiro ativo', 'is_active' => true]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->has('sections', count(LandingSections::TYPES))
            ->has('benefits', 1)
            ->where('benefits.0.title', 'Ativo')
            ->has('services', 1)
            ->has('professionals', 1)
            ->has('gallery', 1)
            ->has('testimonials', 1)
            ->has('faqs', 1)
            ->has('partners', 1)
            ->where('partners.0.name', 'Parceiro ativo')
            // 1 profissional (com especialidade), 1 serviço, 1 unidade matriz — 4 indicadores reais.
            ->has('statistics', 4)
            ->where('statistics.0', ['value' => '1', 'label' => 'Profissional'])
            ->where('contact.phone', '(47) 3222-1122')
        );
});

it('exposes the site latitude and longitude in the contact payload for the map embed', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    SiteSetting::factory()->create([
        'latitude' => '-26.3021000',
        'longitude' => '-48.8456000',
    ]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('contact.latitude', '-26.3021000')
            ->where('contact.longitude', '-48.8456000')
        );
});

it('shows an unlinked professional or service regardless of any operational data', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();

    SiteProfessional::factory()->create(['name' => 'Independente', 'is_active' => true, 'professional_id' => null]);
    SiteService::factory()->create(['name' => 'Independente', 'is_active' => true, 'service_id' => null]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->has('professionals', 1)
            ->has('services', 1)
        );
});

it('hides a linked professional whose operational record is inactive, preserving the promotional row', function () {
    $organization = Organization::factory()->create();
    SiteSetting::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Inactive]);
    $siteProfessional = SiteProfessional::factory()->create(['is_active' => true, 'professional_id' => $professional->id]);

    $this->get('/')->assertInertia(fn ($page) => $page->has('professionals', 0));

    expect(SiteProfessional::query()->find($siteProfessional->id))->not->toBeNull();
});

it('hides a linked professional whose operational record was deleted, preserving the promotional row', function () {
    $organization = Organization::factory()->create();
    SiteSetting::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $siteProfessional = SiteProfessional::factory()->create(['is_active' => true, 'professional_id' => $professional->id]);
    $professional->delete();

    $this->get('/')->assertInertia(fn ($page) => $page->has('professionals', 0));

    expect(SiteProfessional::query()->find($siteProfessional->id))->not->toBeNull();
});

it('hides a linked service whose operational record is inactive or deleted', function () {
    $organization = Organization::factory()->create();
    SiteSetting::factory()->create();
    $inactiveService = Service::factory()->for($organization)->create(['status' => RecordStatus::Inactive]);
    $deletedService = Service::factory()->for($organization)->create();
    $deletedService->delete();

    SiteService::factory()->create(['is_active' => true, 'service_id' => $inactiveService->id]);
    SiteService::factory()->create(['is_active' => true, 'service_id' => $deletedService->id]);

    $this->get('/')->assertInertia(fn ($page) => $page->has('services', 0));
});

it('shows a linked professional and service when the operational record is active', function () {
    $organization = Organization::factory()->create();
    SiteSetting::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $service = Service::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    SiteProfessional::factory()->create(['is_active' => true, 'professional_id' => $professional->id]);
    SiteService::factory()->create(['is_active' => true, 'service_id' => $service->id]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->has('professionals', 1)
        ->has('services', 1)
    );
});

it('orders public benefits by the order field, breaking ties by id', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();

    $second = SiteBenefit::factory()->create(['title' => 'Segundo', 'order' => 1]);
    $first = SiteBenefit::factory()->create(['title' => 'Primeiro', 'order' => 0]);
    $tieBreakerOlder = SiteBenefit::factory()->create(['title' => 'Empate mais antigo', 'order' => 1]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('benefits.0.title', $first->title)
            ->where('benefits.1.title', $second->title)
            ->where('benefits.2.title', $tieBreakerOlder->title)
        );
});

it('excludes inactive faqs from the public payload', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();

    SiteFaq::factory()->create(['question' => 'Ativa', 'is_active' => true]);
    SiteFaq::factory()->inactive()->create(['question' => 'Inativa']);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->has('faqs', 1)
            ->where('faqs.0.question', 'Ativa')
        );
});

it('orders public faqs by the order field, breaking ties by id', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();

    $second = SiteFaq::factory()->create(['question' => 'Segunda', 'order' => 1]);
    $first = SiteFaq::factory()->create(['question' => 'Primeira', 'order' => 0]);
    $tieBreakerOlder = SiteFaq::factory()->create(['question' => 'Empate mais antiga', 'order' => 1]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('faqs.0.question', $first->question)
            ->where('faqs.1.question', $second->question)
            ->where('faqs.2.question', $tieBreakerOlder->question)
        );
});

it('never fabricates a statistic — only counts that are actually greater than zero appear', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();
    // Nenhum profissional, serviço ou unidade cadastrado além do que o
    // factory de Organization já cria por padrão (nenhuma unidade).

    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('statistics', []));
});

it('uses the singular label when a statistic equals exactly one', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    SiteSetting::factory()->create();

    SiteProfessional::factory()->create(['specialty' => null]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('statistics', [
                ['value' => '1', 'label' => 'Profissional'],
                ['value' => '1', 'label' => 'Unidade'],
            ])
        );
});

it('uses the plural label once a statistic is greater than one', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();

    SiteProfessional::factory()->count(2)->create(['specialty' => null]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('statistics.0', ['value' => '2', 'label' => 'Profissionais'])
        );
});
