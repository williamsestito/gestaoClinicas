<?php

declare(strict_types=1);

use App\Models\PatientUser;
use App\Notifications\ResetPatientPasswordNotification;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// O rate limit real (throttle:patient-password-reset) usa o cache Redis do
// ambiente de teste, que não é resetado por RefreshDatabase e vaza entre
// execuções da suíte — mesmo padrão de AppointmentRequestSubmissionTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('renders the forgot-password screen', function () {
    $this->get('/portal/esqueci-senha')->assertOk();
});

it('sends a reset link notification against the patient_users broker', function () {
    Notification::fake();

    $patientUser = PatientUser::factory()->create();

    $this->post('/portal/esqueci-senha', ['email' => $patientUser->email]);

    Notification::assertSentTo($patientUser, ResetPatientPasswordNotification::class);
});

it('resets the password with a valid token and lets the user log in with the new password', function () {
    Notification::fake();

    $patientUser = PatientUser::factory()->create(['password' => Hash::make('old-password')]);

    $this->post('/portal/esqueci-senha', ['email' => $patientUser->email]);

    Notification::assertSentTo($patientUser, ResetPatientPasswordNotification::class, function ($notification) use ($patientUser) {
        $this->post('/portal/redefinir-senha', [
            'token' => $notification->token,
            'email' => $patientUser->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/login');

        return true;
    });

    $this->post('/login', [
        'email' => $patientUser->email,
        'password' => 'new-password',
    ])->assertRedirect('/portal');
});

it('rotates the remember token on reset, invalidating any stolen remember-me cookie', function () {
    Notification::fake();

    $patientUser = PatientUser::factory()->create([
        'password' => Hash::make('old-password'),
        'remember_token' => 'stolen-token-1234567890',
    ]);

    $this->post('/portal/esqueci-senha', ['email' => $patientUser->email]);

    Notification::assertSentTo($patientUser, ResetPatientPasswordNotification::class, function ($notification) use ($patientUser) {
        $this->post('/portal/redefinir-senha', [
            'token' => $notification->token,
            'email' => $patientUser->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        return true;
    });

    expect($patientUser->fresh()->remember_token)->not->toBe('stolen-token-1234567890');
});
