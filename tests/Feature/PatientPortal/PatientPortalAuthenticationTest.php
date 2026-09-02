<?php

declare(strict_types=1);

use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// O rate limit real (throttle:login) usa o cache Redis do ambiente de
// teste, que não é resetado por RefreshDatabase e vaza entre execuções da
// suíte — mesmo padrão de AppointmentRequestSubmissionTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

// O login em si (POST /login reconhecendo staff ou paciente pelo e-mail)
// é coberto por tests/Feature/Security/SessionSecurityTest.php — este
// arquivo cobre só o que é específico do guard "patient": logout e
// isolamento entre guards.
it('logs out and invalidates the patient session, redirecting to the general login screen', function () {
    $patientUser = PatientUser::factory()->create();

    $this->actingAs($patientUser, 'patient');
    expect(Auth::guard('patient')->check())->toBeTrue();

    $this->post('/portal/logout')->assertRedirect('/login');

    expect(Auth::guard('patient')->check())->toBeFalse();
});

it('lets an already-authenticated patient also log into the staff guard via /login, without dropping the patient session', function () {
    $staffUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $patientUser = PatientUser::factory()->create();

    $this->actingAs($patientUser, 'patient');

    $this->post('/login', [
        'email' => $staffUser->email,
        'password' => 'password',
    ]);

    expect(Auth::guard('patient')->check())->toBeTrue()
        ->and(Auth::guard('patient')->id())->toBe($patientUser->id)
        ->and(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe($staffUser->id);
});

// Laravel's Illuminate\Auth\Middleware\RedirectIfAuthenticated (Fortify's
// "guest:web" on POST /login) redirects away BEFORE the request body is
// even inspected whenever the web guard is already authenticated — so an
// already-logged-in staff member can never reach /login to also start a
// patient session in the same browser. This is standard framework
// behaviour, not something this app's login unification tries to bypass;
// the previous separate /portal/login page allowed it only because it used
// a "patient"-scoped guest check instead.
it('redirects an already-authenticated staff member away from /login, blocking a second (patient) session', function () {
    $staffUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $patientUser = PatientUser::factory()->create(['password' => Hash::make('secret123')]);

    $this->actingAs($staffUser, 'web');

    $this->post('/login', [
        'email' => $patientUser->email,
        'password' => 'secret123',
    ])->assertRedirect();

    expect(Auth::guard('patient')->check())->toBeFalse();
});
