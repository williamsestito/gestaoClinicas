<?php

declare(strict_types=1);

use App\Models\User;

it('renders the register page for a guest', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Register'));
});

it('no longer accepts a POST to /register — staff self-registration was removed', function () {
    $this->post('/register', [
        'name' => 'Fulano de Tal',
        'email' => 'fulano@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertMethodNotAllowed();

    expect(User::query()->where('email', 'fulano@example.com')->exists())->toBeFalse();
});

it('redirects an already authenticated staff user away from the register page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/register')->assertRedirect(route('dashboard'));
});
