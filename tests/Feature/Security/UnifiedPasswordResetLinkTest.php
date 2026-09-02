<?php

declare(strict_types=1);

use App\Models\PatientUser;
use App\Models\User;
use App\Notifications\ResetPatientPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;

// O rate limit real (throttle:forgot-password-link) usa o cache Redis do
// ambiente de teste — mesmo padrão de AppointmentRequestSubmissionTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('sends the staff reset notification for a staff email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/esqueci-senha', ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('sends the patient reset notification for a patient email', function () {
    Notification::fake();
    $patientUser = PatientUser::factory()->create();

    $this->post('/esqueci-senha', ['email' => $patientUser->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($patientUser, ResetPatientPasswordNotification::class);
});

it('shows the same success message for an email that belongs to no account, without sending anything', function () {
    Notification::fake();

    $response = $this->post('/esqueci-senha', ['email' => 'nobody@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    $staffMessage = $response->getSession()->get('status');

    Notification::assertNothingSent();

    // Mesma mensagem de sucesso, exista ou não a conta.
    $this->post('/esqueci-senha', ['email' => User::factory()->create()->email]);
    expect(session('status'))->toBe($staffMessage);
});

it('prefers the staff account when the same email exists in both tables', function () {
    Notification::fake();
    $user = User::factory()->create();
    PatientUser::factory()->create(['email' => $user->email]);

    $this->post('/esqueci-senha', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertNotSentTo(
        PatientUser::query()->where('email', $user->email)->firstOrFail(),
        ResetPatientPasswordNotification::class,
    );
});
