<?php

declare(strict_types=1);

use App\Models\OrganizationMembership;
use App\Models\SiteSetting;
use App\Models\User;

it('lets the owner update the site content', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.site.update'), [
            'title' => 'Clínica Exemplo',
            'description' => 'Cuidando de você.',
            'cta_text' => 'Agende sua consulta',
            'cta_url' => 'https://wa.me/554730000000',
            'about_text' => 'Uma clínica dedicada ao seu bem-estar.',
            'facebook_url' => 'https://facebook.com/clinicaexemplo',
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $site = SiteSetting::query()->first();

    expect($site)->not->toBeNull()
        ->and($site->title)->toBe('Clínica Exemplo')
        ->and($site->cta_text)->toBe('Agende sua consulta')
        ->and($site->facebook_url)->toBe('https://facebook.com/clinicaexemplo')
        ->and($site->is_published)->toBeFalse();
});

it('blocks a non-owner without site.update permission from updating the site', function () {
    $ctx = ownerActingInOrganization();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($member)
        ->put(route('settings.site.update'), ['title' => 'Não deveria funcionar'])
        ->assertForbidden();
});

it('lets the owner publish and unpublish the site', function () {
    $ctx = ownerActingInOrganization();
    $site = SiteSetting::factory()->create(['is_published' => false]);

    $this->actingAs($ctx['user'])
        ->patch(route('settings.site.publish'))
        ->assertSessionHasNoErrors();

    expect($site->fresh()->is_published)->toBeTrue();

    $this->actingAs($ctx['user'])
        ->patch(route('settings.site.unpublish'))
        ->assertSessionHasNoErrors();

    expect($site->fresh()->is_published)->toBeFalse();
});

it('refuses to publish when no site content exists yet', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->patch(route('settings.site.publish'))
        ->assertSessionHasErrors('site');
});
