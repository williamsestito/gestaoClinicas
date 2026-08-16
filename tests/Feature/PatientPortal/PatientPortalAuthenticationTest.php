<?php

declare(strict_types=1);

use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// O rate limit real (throttle:patient-login) usa o cache Redis do ambiente
// de teste, que não é resetado por RefreshDatabase e vaza entre execuções
// da suíte — mesmo padrão de AppointmentRequestSubmissionTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('logs a patient user in with valid credentials', function () {
    $patientUser = PatientUser::factory()->create(['password' => Hash::make('secret123')]);

    $this->post('/portal/login', [
        'email' => $patientUser->email,
        'password' => 'secret123',
    ])->assertRedirect('/portal');

    expect(Auth::guard('patient')->check())->toBeTrue()
        ->and(Auth::guard('patient')->id())->toBe($patientUser->id);
});

it('rejects an invalid password', function () {
    $patientUser = PatientUser::factory()->create(['password' => Hash::make('secret123')]);

    $this->post('/portal/login', [
        'email' => $patientUser->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('patient')->check())->toBeFalse();
});

it('blocks a deactivated patient user account from logging in', function () {
    $patientUser = PatientUser::factory()->inactive()->create(['password' => Hash::make('secret123')]);

    $this->post('/portal/login', [
        'email' => $patientUser->email,
        'password' => 'secret123',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('patient')->check())->toBeFalse();
});

it('logs out and invalidates the patient session', function () {
    $patientUser = PatientUser::factory()->create();

    $this->actingAs($patientUser, 'patient');
    expect(Auth::guard('patient')->check())->toBeTrue();

    $this->post('/portal/logout')->assertRedirect('/portal/login');

    expect(Auth::guard('patient')->check())->toBeFalse();
});

it('keeps the staff (web) guard session untouched by a patient login, and vice versa', function () {
    $staffUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $patientUser = PatientUser::factory()->create(['password' => Hash::make('secret123')]);

    $this->actingAs($staffUser, 'web');

    $this->post('/portal/login', [
        'email' => $patientUser->email,
        'password' => 'secret123',
    ]);

    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe($staffUser->id)
        ->and(Auth::guard('patient')->check())->toBeTrue()
        ->and(Auth::guard('patient')->id())->toBe($patientUser->id);
});
