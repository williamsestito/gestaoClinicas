<?php

declare(strict_types=1);

use App\Models\User;

it('regenerates the session id on successful login', function () {
    $user = User::factory()->create(['password' => bcrypt('senha-valida-123')]);

    $this->get('/login');
    $idBeforeLogin = session()->getId();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'senha-valida-123',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($idBeforeLogin);
});

it('invalidates the session id on logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard');
    $idBeforeLogout = session()->getId();

    $this->post('/logout')->assertRedirect();

    $this->assertGuest();
    expect(session()->getId())->not->toBe($idBeforeLogout);
});

it('marks the session cookie as HttpOnly', function () {
    $response = $this->get('/');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe(config('session.same_site'));
});

it('configures session security defaults consistently with the environment', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});
