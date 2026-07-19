<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns address data for a valid postal code', function () {
    Http::fake([
        'cep.awesomeapi.com.br/*' => Http::response([
            'cep' => '01310930',
            'address' => 'Avenida Paulista, 2100',
            'state' => 'SP',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'city_ibge' => '3550308',
        ]),
        '*' => Http::response([], 500),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')
        ->assertOk()
        ->assertJson(['city' => 'São Paulo', 'state' => 'SP', 'source' => 'awesomeapi']);
});

it('returns 404 when none of the providers find the postal code', function () {
    Http::fake([
        'cep.awesomeapi.com.br/*' => Http::response(['code' => 'not_found'], 404),
        'cdn.apicep.com/*' => Http::response(['code' => 'not_found', 'status' => 404], 404),
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/00000000')
        ->assertNotFound()
        ->assertJson(['message' => 'CEP não encontrado.']);
});

it('does not break when every external provider times out', function () {
    Http::fake([
        '*' => fn () => throw new ConnectionException('timed out'),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')->assertNotFound();
});

it('caches a successful postal code lookup across all providers', function () {
    Http::fake([
        'cep.awesomeapi.com.br/*' => Http::response([
            'address' => 'Avenida Paulista, 2100',
            'state' => 'SP',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
        ]),
        '*' => Http::response([], 500),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')->assertOk();
    $this->actingAs($user)->getJson('/cep/01310930')->assertOk();

    Http::assertSentCount(1);
});

afterEach(function () {
    Cache::flush();
});
