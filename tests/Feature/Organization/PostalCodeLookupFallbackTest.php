<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakeAwesomeApiSuccess(): array
{
    return [
        'cep.awesomeapi.com.br/*' => Http::response([
            'cep' => '01310930',
            'address' => 'Avenida Paulista, 2100',
            'state' => 'SP',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'city_ibge' => '3550308',
        ]),
    ];
}

function fakeAwesomeApiNotFound(): array
{
    return [
        'cep.awesomeapi.com.br/*' => Http::response(['code' => 'not_found', 'message' => 'CEP nao encontrado'], 404),
    ];
}

function fakeApiCepSuccess(): array
{
    return [
        'cdn.apicep.com/*' => Http::response([
            'code' => '01310-930',
            'state' => 'SP',
            'city' => 'São Paulo',
            'district' => 'Bela Vista',
            'address' => 'Avenida Paulista, 2100',
            'status' => 200,
            'ok' => true,
            'statusText' => 'ok',
        ]),
    ];
}

function fakeApiCepNotFound(): array
{
    return [
        'cdn.apicep.com/*' => Http::response(['code' => 'not_found', 'status' => 404], 404),
    ];
}

function fakeViaCepSuccess(): array
{
    return [
        'viacep.com.br/*' => Http::response([
            'cep' => '01310-930',
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ];
}

function fakeViaCepNotFound(): array
{
    return [
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ];
}

it('uses the AwesomeAPI CEP result and never calls the other providers when it succeeds', function () {
    Http::fake([
        ...fakeAwesomeApiSuccess(),
        'cdn.apicep.com/*' => Http::response([], 500),
        'viacep.com.br/*' => Http::response([], 500),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')
        ->assertOk()
        ->assertJson([
            'street' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'source' => 'awesomeapi',
        ]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'apicep.com') || str_contains($request->url(), 'viacep.com.br'));
});

it('falls back to API CEP when AwesomeAPI CEP fails', function () {
    Http::fake([
        ...fakeAwesomeApiNotFound(),
        ...fakeApiCepSuccess(),
        'viacep.com.br/*' => Http::response([], 500),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')
        ->assertOk()
        ->assertJson(['city' => 'São Paulo', 'source' => 'apicep']);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'viacep.com.br'));
});

it('falls back to ViaCEP when both AwesomeAPI CEP and API CEP fail', function () {
    Http::fake([
        ...fakeAwesomeApiNotFound(),
        ...fakeApiCepNotFound(),
        ...fakeViaCepSuccess(),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')
        ->assertOk()
        ->assertJson(['city' => 'São Paulo', 'source' => 'viacep']);
});

it('returns a generic not-found message, without leaking provider details, when all three fail', function () {
    Http::fake([
        ...fakeAwesomeApiNotFound(),
        ...fakeApiCepNotFound(),
        ...fakeViaCepNotFound(),
    ]);

    $user = actingOwnerWithActiveContext();

    $response = $this->actingAs($user)->getJson('/cep/00000000')
        ->assertNotFound();

    expect($response->json())->toBe(['message' => 'CEP não encontrado.']);
});

it('requests API CEP with the hyphenated format, unlike the other two providers', function () {
    Http::fake([
        ...fakeAwesomeApiNotFound(),
        ...fakeApiCepSuccess(),
        'viacep.com.br/*' => Http::response([], 500),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'cdn.apicep.com/file/apicep/01310-930.json'));
});

it('caches the winning result so a repeated lookup does not call any provider again', function () {
    Http::fake([
        ...fakeAwesomeApiNotFound(),
        ...fakeApiCepSuccess(),
        'viacep.com.br/*' => Http::response([], 500),
    ]);

    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->getJson('/cep/01310930')->assertOk();
    Http::assertSentCount(2);

    $this->actingAs($user)->getJson('/cep/01310930')->assertOk();
    Http::assertSentCount(2);
});

afterEach(function () {
    Cache::flush();
});
