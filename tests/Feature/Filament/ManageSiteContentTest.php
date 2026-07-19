<?php

declare(strict_types=1);

use App\Enums\IndexingPolicy;
use App\Enums\SchemaBusinessType;
use App\Filament\Pages\ManageSiteContent;
use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;

it('denies access to a non-platform-admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/manage-site-content')->assertForbidden();
});

it('loads the page and fills the form with the existing record', function () {
    $admin = User::factory()->platformAdmin()->create();
    SiteSetting::factory()->create(['title' => 'Minha Clínica']);

    Livewire::actingAs($admin)
        ->test(ManageSiteContent::class)
        ->assertFormSet(['title' => 'Minha Clínica']);
});

it('creates the record on first save when none exists yet', function () {
    $admin = User::factory()->platformAdmin()->create();

    expect(SiteSetting::query()->count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(ManageSiteContent::class)
        ->fillForm([
            'title' => 'Gestão de Clínicas',
            'description' => 'Descrição de teste.',
            'primary_color' => '#0F766E',
            'secondary_color' => '#F59E0B',
        ])
        ->call('save')
        ->assertNotified();

    expect(SiteSetting::query()->count())->toBe(1)
        ->and(SiteSetting::query()->first()->title)->toBe('Gestão de Clínicas');
});

it('updates the existing record on save instead of creating a second one', function () {
    $admin = User::factory()->platformAdmin()->create();
    $site = SiteSetting::factory()->create(['title' => 'Título antigo']);

    Livewire::actingAs($admin)
        ->test(ManageSiteContent::class)
        ->fillForm(['title' => 'Título novo'])
        ->call('save')
        ->assertNotified();

    expect(SiteSetting::query()->count())->toBe(1)
        ->and($site->fresh()->title)->toBe('Título novo');
});

it('requires a title', function () {
    $admin = User::factory()->platformAdmin()->create();

    Livewire::actingAs($admin)
        ->test(ManageSiteContent::class)
        ->fillForm(['title' => ''])
        ->call('save')
        ->assertHasFormErrors(['title' => 'required']);
});

it('saves the SEO fields correctly', function () {
    $admin = User::factory()->platformAdmin()->create();

    Livewire::actingAs($admin)
        ->test(ManageSiteContent::class)
        ->fillForm([
            'title' => 'Gestão de Clínicas',
            'official_domain' => 'clinicaexemplo.com.br',
            'schema_type' => SchemaBusinessType::MedicalClinic->value,
            'meta_title' => 'Clínica Exemplo — Cuidando de você',
            'meta_description' => 'Atendimento humanizado em Joinville/SC.',
            'indexing_policy' => IndexingPolicy::Index->value,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $site = SiteSetting::query()->first();

    expect($site->official_domain)->toBe('clinicaexemplo.com.br')
        ->and($site->schema_type)->toBe(SchemaBusinessType::MedicalClinic)
        ->and($site->meta_title)->toBe('Clínica Exemplo — Cuidando de você')
        ->and($site->indexing_policy)->toBe(IndexingPolicy::Index);
});

it('rejects a malformed official domain', function () {
    $admin = User::factory()->platformAdmin()->create();

    Livewire::actingAs($admin)
        ->test(ManageSiteContent::class)
        ->fillForm([
            'title' => 'Gestão de Clínicas',
            'official_domain' => 'https://clinicaexemplo.com.br/pagina',
        ])
        ->call('save')
        ->assertHasFormErrors(['official_domain']);
});
