<?php

declare(strict_types=1);

use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

it('logs a patient into the patient guard when submitting the staff login form', function () {
    $patientUser = PatientUser::factory()->create(['password' => Hash::make('senha-valida-123')]);

    $this->get('/login');
    $idBeforeLogin = session()->getId();

    $this->post('/login', [
        'email' => $patientUser->email,
        'password' => 'senha-valida-123',
    ])->assertRedirect(route('patient-portal.dashboard'));

    $this->assertGuest('web');
    $this->assertAuthenticatedAs($patientUser, 'patient');
    expect(session()->getId())->not->toBe($idBeforeLogin);
});

it('blocks an inactive patient from logging in through the staff login form', function () {
    $patientUser = PatientUser::factory()->inactive()->create(['password' => Hash::make('senha-valida-123')]);

    $this->post('/login', [
        'email' => $patientUser->email,
        'password' => 'senha-valida-123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('patient');
});

it('prefers a staff account over a patient account sharing the same email', function () {
    $user = User::factory()->create(['password' => bcrypt('senha-do-staff')]);
    PatientUser::factory()->create(['email' => $user->email, 'password' => Hash::make('senha-do-paciente')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'senha-do-staff',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $this->assertGuest('patient');
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
