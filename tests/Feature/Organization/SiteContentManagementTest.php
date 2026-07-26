<?php

declare(strict_types=1);

use App\Actions\Organization\UpdateSiteContentAction;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets the owner upload a hero image (banner) for the site', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image' => UploadedFile::fake()->image('hero.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $site = SiteSetting::query()->first();

    expect($site)->not->toBeNull()
        ->and($site->hero_image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($site->hero_image_path);
});

it('accepts JPEG, PNG and WebP hero images', function (string $file) {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image' => UploadedFile::fake()->image($file),
        ])
        ->assertSessionHasNoErrors();
})->with([
    'hero.jpg',
    'hero.png',
    'hero.webp',
]);

it('rejects hero image formats other than JPEG, PNG and WebP', function (string $file) {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image' => UploadedFile::fake()->image($file),
        ])
        ->assertSessionHasErrors('hero_image');

    expect(SiteSetting::query()->first())->toBeNull();
})->with([
    'hero.gif',
    'hero.bmp',
]);

it('rejects an SVG file as the hero image', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $svg = UploadedFile::fake()->createWithContent(
        'hero.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    );

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image' => $svg,
        ])
        ->assertSessionHasErrors('hero_image');
});

it('rejects a hero image above the size limit', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image' => UploadedFile::fake()->image('hero.jpg')->size(6000),
        ])
        ->assertSessionHasErrors('hero_image');
});

it('rejects a malformed file disguised as an image', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image' => UploadedFile::fake()->create('hero.jpg', 100, 'image/jpeg'),
        ])
        ->assertSessionHasErrors('hero_image');
});

it('replaces the previous hero image and removes the old file from storage', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'hero_image' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $firstPath = SiteSetting::query()->first()->hero_image_path;
    Storage::disk('public')->assertExists($firstPath);

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'hero_image' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $secondPath = SiteSetting::query()->first()->hero_image_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertExists($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
});

it('keeps the previous hero image untouched when persisting the new one fails', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'hero_image' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $site = SiteSetting::query()->first();
    $originalPath = $site->hero_image_path;

    SiteSetting::saving(function () {
        throw new RuntimeException('Simulated database failure.');
    });

    try {
        $this->expectException(RuntimeException::class);

        app(UpdateSiteContentAction::class)->handle(
            siteSetting: $site,
            data: ['title' => 'Clínica Exemplo'],
            files: ['hero_image' => UploadedFile::fake()->image('second.jpg')],
        );
    } finally {
        SiteSetting::unsetEventDispatcher();
    }

    expect($site->fresh()->hero_image_path)->toBe($originalPath);
    Storage::disk('public')->assertExists($originalPath);
    Storage::disk('public')->allFiles('site-content')->each(
        fn (string $path) => expect($path)->toBe($originalPath),
    );
});

it('lets the owner remove the hero image, clearing the field and the file without touching other data', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'cta_text' => 'Agende sua consulta',
        'hero_image' => UploadedFile::fake()->image('hero.jpg'),
    ]);

    $path = SiteSetting::query()->first()->hero_image_path;
    Storage::disk('public')->assertExists($path);

    $this->actingAs($ctx['user'])
        ->delete(route('settings.site.hero-image.destroy'))
        ->assertSessionHasNoErrors()
        ->assertStatus(302);

    $site = SiteSetting::query()->first();

    expect($site->hero_image_path)->toBeNull()
        ->and($site->title)->toBe('Clínica Exemplo')
        ->and($site->cta_text)->toBe('Agende sua consulta');

    Storage::disk('public')->assertMissing($path);
});

it('does nothing when removing a hero image that was never set', function () {
    $ctx = ownerActingInOrganization();
    SiteSetting::factory()->create(['hero_image_path' => null]);

    $this->actingAs($ctx['user'])
        ->delete(route('settings.site.hero-image.destroy'))
        ->assertSessionHasNoErrors();

    expect(SiteSetting::query()->first()->hero_image_path)->toBeNull();
});

it('refuses to remove a hero image when no site content exists yet', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->delete(route('settings.site.hero-image.destroy'))
        ->assertSessionHasErrors('hero_image');
});

it('blocks a non-owner without site.update permission from removing the hero image', function () {
    $ctx = ownerActingInOrganization();
    SiteSetting::factory()->create(['hero_image_path' => 'site-content/hero.jpg']);
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($member)
        ->delete(route('settings.site.hero-image.destroy'))
        ->assertForbidden();
});

it('rejects a javascript: scheme in cta_url', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->put(route('settings.site.update'), [
            'title' => 'Clínica Exemplo',
            'cta_url' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('cta_url');
});

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

it('lets the owner upload a logo and a favicon for the site', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'favicon' => UploadedFile::fake()->image('favicon.png'),
        ])
        ->assertSessionHasNoErrors();

    $site = SiteSetting::query()->first();

    expect($site->logo_path)->not->toBeNull()
        ->and($site->favicon_path)->not->toBeNull();

    Storage::disk('public')->assertExists($site->logo_path);
    Storage::disk('public')->assertExists($site->favicon_path);
});

it('rejects logo and favicon formats other than JPEG, PNG and WebP', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'logo' => UploadedFile::fake()->image('logo.gif'),
            'favicon' => UploadedFile::fake()->image('favicon.bmp'),
        ])
        ->assertSessionHasErrors(['logo', 'favicon']);

    expect(SiteSetting::query()->first())->toBeNull();
});

it('replaces the previous logo and favicon, removing the old files from storage', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'logo' => UploadedFile::fake()->image('first-logo.png'),
        'favicon' => UploadedFile::fake()->image('first-favicon.png'),
    ]);

    $site = SiteSetting::query()->first();
    $firstLogoPath = $site->logo_path;
    $firstFaviconPath = $site->favicon_path;

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'logo' => UploadedFile::fake()->image('second-logo.png'),
        'favicon' => UploadedFile::fake()->image('second-favicon.png'),
    ]);

    $site->refresh();

    expect($site->logo_path)->not->toBe($firstLogoPath)
        ->and($site->favicon_path)->not->toBe($firstFaviconPath);

    Storage::disk('public')->assertExists($site->logo_path);
    Storage::disk('public')->assertExists($site->favicon_path);
    Storage::disk('public')->assertMissing($firstLogoPath);
    Storage::disk('public')->assertMissing($firstFaviconPath);
});

it('keeps the previous logo untouched when persisting the new one fails', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'logo' => UploadedFile::fake()->image('first-logo.png'),
    ]);

    $site = SiteSetting::query()->first();
    $originalPath = $site->logo_path;

    SiteSetting::saving(function () {
        throw new RuntimeException('Simulated database failure.');
    });

    try {
        $this->expectException(RuntimeException::class);

        app(UpdateSiteContentAction::class)->handle(
            siteSetting: $site,
            data: ['title' => 'Clínica Exemplo'],
            files: ['logo' => UploadedFile::fake()->image('second-logo.png')],
        );
    } finally {
        SiteSetting::unsetEventDispatcher();
    }

    expect($site->fresh()->logo_path)->toBe($originalPath);
    Storage::disk('public')->assertExists($originalPath);
});

it('lets the owner remove the logo and the favicon independently, preserving other data', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'cta_text' => 'Agende sua consulta',
        'logo' => UploadedFile::fake()->image('logo.png'),
        'favicon' => UploadedFile::fake()->image('favicon.png'),
    ]);

    $site = SiteSetting::query()->first();
    $logoPath = $site->logo_path;
    $faviconPath = $site->favicon_path;

    $this->actingAs($ctx['user'])
        ->delete(route('settings.site.logo.destroy'))
        ->assertSessionHasNoErrors();

    $site->refresh();

    expect($site->logo_path)->toBeNull()
        ->and($site->favicon_path)->not->toBeNull()
        ->and($site->title)->toBe('Clínica Exemplo')
        ->and($site->cta_text)->toBe('Agende sua consulta');

    Storage::disk('public')->assertMissing($logoPath);
    Storage::disk('public')->assertExists($faviconPath);

    $this->actingAs($ctx['user'])
        ->delete(route('settings.site.favicon.destroy'))
        ->assertSessionHasNoErrors();

    $site->refresh();

    expect($site->favicon_path)->toBeNull();
    Storage::disk('public')->assertMissing($faviconPath);
});

it('blocks a non-owner without site.update permission from removing the logo or the favicon', function () {
    $ctx = ownerActingInOrganization();
    SiteSetting::factory()->create([
        'logo_path' => 'site-content/logo.png',
        'favicon_path' => 'site-content/favicon.png',
    ]);
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($member)->delete(route('settings.site.logo.destroy'))->assertForbidden();
    $this->actingAs($member)->delete(route('settings.site.favicon.destroy'))->assertForbidden();
});

it('audits hero image, logo and favicon changes with before/after paths, never binary content', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'hero_image' => UploadedFile::fake()->image('hero.jpg'),
        'logo' => UploadedFile::fake()->image('logo.png'),
        'favicon' => UploadedFile::fake()->image('favicon.png'),
    ]);

    $site = SiteSetting::query()->first();

    $uploadLog = AuditLog::query()
        ->where('auditable_id', $site->id)
        ->latest('id')
        ->firstOrFail();

    expect(array_key_exists('hero_image_path', $uploadLog->before_data))->toBeTrue()
        ->and($uploadLog->before_data['hero_image_path'])->toBeNull()
        ->and($uploadLog->after_data['hero_image_path'])->toBe($site->hero_image_path)
        ->and($uploadLog->after_data['logo_path'])->toBe($site->logo_path)
        ->and($uploadLog->after_data['favicon_path'])->toBe($site->favicon_path);

    foreach ([$uploadLog->before_data, $uploadLog->after_data] as $data) {
        foreach ($data as $value) {
            if (is_string($value)) {
                expect($value)->not->toContain('data:image')
                    ->and(strlen($value))->toBeLessThan(255);
            }
        }
    }

    $this->actingAs($ctx['user'])->delete(route('settings.site.logo.destroy'));

    $removalLog = AuditLog::query()
        ->where('auditable_id', $site->id)
        ->latest('id')
        ->firstOrFail();

    expect($removalLog->before_data['logo_path'])->toBe($site->logo_path)
        ->and($removalLog->after_data['logo_path'])->toBeNull();
});

it('generates real favicon variants from an uploaded image, without adding any new dependency', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'favicon' => UploadedFile::fake()->image('favicon.png', 512, 512),
        ])
        ->assertSessionHasNoErrors();

    $site = SiteSetting::query()->first();

    expect($site->favicon_variants)->not->toBeNull()
        ->and($site->favicon_variants)->toHaveKeys(['16', '32', '48', '180', '192']);

    foreach ($site->favicon_variants as $size => $path) {
        Storage::disk('public')->assertExists($path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));
        expect($width)->toBe((int) $size)->and($height)->toBe((int) $size);
    }
});

it('renders the generated favicon as real <link> tags on the public landing page', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'favicon' => UploadedFile::fake()->image('favicon.png', 512, 512),
    ]);

    $site = SiteSetting::query()->first();
    $urls = $site->faviconUrls();

    $html = $this->get('/')->getContent();

    expect($html)->toContain('rel="icon" type="image/png" sizes="32x32" href="'.$urls['32'])
        ->and($html)->toContain('rel="icon" type="image/png" sizes="16x16" href="'.$urls['16'])
        ->and($html)->toContain('rel="apple-touch-icon" sizes="180x180" href="'.$urls['180']);
});

it('falls back to the static default favicon tags when none was uploaded, never an empty tag', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('href="/favicon.ico"')
        ->and($html)->not->toContain('href=""');
});

it('replaces favicon variants when a new favicon is uploaded, removing the old files', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'favicon' => UploadedFile::fake()->image('first-favicon.png'),
    ]);

    $firstVariants = SiteSetting::query()->first()->favicon_variants;

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'favicon' => UploadedFile::fake()->image('second-favicon.png'),
    ]);

    $secondVariants = SiteSetting::query()->first()->favicon_variants;

    expect($secondVariants)->not->toBe($firstVariants);

    foreach ($firstVariants as $path) {
        Storage::disk('public')->assertMissing($path);
    }
    foreach ($secondVariants as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('removes favicon variants when the favicon is deleted', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])->post(route('settings.site.update'), [
        '_method' => 'put',
        'title' => 'Clínica Exemplo',
        'favicon' => UploadedFile::fake()->image('favicon.png'),
    ]);

    $variants = SiteSetting::query()->first()->favicon_variants;

    $this->actingAs($ctx['user'])->delete(route('settings.site.favicon.destroy'))->assertSessionHasNoErrors();

    expect(SiteSetting::query()->first()->favicon_variants)->toBeNull();

    foreach ($variants as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('lets the owner upload, preview and remove a dedicated mobile banner', function () {
    Storage::fake('public');
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->post(route('settings.site.update'), [
            '_method' => 'put',
            'title' => 'Clínica Exemplo',
            'hero_image_mobile' => UploadedFile::fake()->image('banner-mobile.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $site = SiteSetting::query()->first();
    expect($site->hero_image_mobile_path)->not->toBeNull();
    Storage::disk('public')->assertExists($site->hero_image_mobile_path);

    $mobilePath = $site->hero_image_mobile_path;

    $this->actingAs($ctx['user'])
        ->delete(route('settings.site.hero-image-mobile.destroy'))
        ->assertSessionHasNoErrors();

    expect($site->fresh()->hero_image_mobile_path)->toBeNull();
    Storage::disk('public')->assertMissing($mobilePath);
});
