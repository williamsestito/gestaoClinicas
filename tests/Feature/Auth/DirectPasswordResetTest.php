<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;

afterEach(function () {
    app()['env'] = 'testing';
});

it('is not available outside local/testing environments', function () {
    // Fora de local/testing, o bypass automático de CSRF em testes
    // também desliga — desabilita o middleware para testar só o
    // bloqueio de ambiente do controller.
    $this->withoutMiddleware(PreventRequestForgery::class);
    app()['env'] = 'production';
    $user = User::factory()->create();

    $this->post(route('password.direct-confirm-email'), ['email' => $user->email])
        ->assertNotFound();

    $this->post(route('password.direct-reset'), [
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertNotFound();
});

it('rejects confirmation for an email that does not exist', function () {
    $this->post(route('password.direct-confirm-email'), ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors('email');
});

it('confirms a known email and exposes it back to the forgot-password page', function () {
    $user = User::factory()->create();

    $this->post(route('password.direct-confirm-email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('confirmedEmail', $user->email);

    $this->get(route('password.request'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/ForgotPassword')
            ->where('directPasswordResetEnabled', true)
            ->where('confirmedEmail', $user->email)
        );
});

it('rejects the direct reset for an email that does not exist', function () {
    $this->post(route('password.direct-reset'), [
        'email' => 'nobody@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});

it('rejects the direct reset when the password confirmation does not match', function () {
    $user = User::factory()->create();

    $this->post(route('password.direct-reset'), [
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'somethingelse',
    ])->assertSessionHasErrors('password');
});

it('rewrites the password hash directly and lets the user log in with the new password', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->post(route('password.direct-reset'), [
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->fresh()->password))->toBeFalse();
});
