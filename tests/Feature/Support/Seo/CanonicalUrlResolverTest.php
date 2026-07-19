<?php

declare(strict_types=1);

use App\Models\SiteSetting;
use App\Support\Seo\CanonicalUrlResolver;

afterEach(function () {
    app()['env'] = 'testing';
});

it('uses APP_URL when no site setting exists, regardless of environment', function () {
    config(['app.url' => 'http://localhost:8080']);

    $url = (new CanonicalUrlResolver)->resolve(null, '/');

    expect($url)->toBe('http://localhost:8080/');
});

it('uses APP_URL outside production even when an official domain is configured', function () {
    config(['app.url' => 'http://localhost:8080']);
    $siteSetting = new SiteSetting(['official_domain' => 'clinicaexemplo.com.br']);

    // O ambiente de teste nunca é "production".
    $url = (new CanonicalUrlResolver)->resolve($siteSetting, '/');

    expect($url)->toBe('http://localhost:8080/');
});

it('builds the path correctly regardless of leading slashes', function () {
    config(['app.url' => 'http://localhost:8080']);

    expect((new CanonicalUrlResolver)->resolve(null, 'sitemap.xml'))
        ->toBe('http://localhost:8080/sitemap.xml')
        ->and((new CanonicalUrlResolver)->resolve(null, '/sitemap.xml'))
        ->toBe('http://localhost:8080/sitemap.xml');
});

it('uses the official domain with https when running in production', function () {
    app()['env'] = 'production';
    config(['app.url' => 'http://localhost:8080']);
    $siteSetting = new SiteSetting(['official_domain' => 'clinicaexemplo.com.br']);

    $url = (new CanonicalUrlResolver)->resolve($siteSetting, '/');

    expect($url)->toBe('https://clinicaexemplo.com.br/');
});

it('falls back to APP_URL in production when no official domain is configured', function () {
    app()['env'] = 'production';
    config(['app.url' => 'http://localhost:8080']);

    $url = (new CanonicalUrlResolver)->resolve(null, '/');

    expect($url)->toBe('http://localhost:8080/');
});
