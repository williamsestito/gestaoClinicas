<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request) {
            $email = (string) $request->input(Fortify::username());
            $password = (string) $request->input('password');

            $user = User::query()->where(Fortify::username(), $email)->first();

            if ($user && Hash::check($password, $user->password)) {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        Fortify::username() => 'Esta conta está desativada. Entre em contato com o suporte se precisar reativá-la.',
                    ]);
                }

                return $user;
            }

            $this->authenticatePatientOrFail($request, $email, $password);

            return null;
        });
    }

    /**
     * Reconhece, pelo e-mail digitado em /login, uma conta de paciente do
     * portal (guard "patient", tabela separada de `users`) e autentica
     * direto no guard certo — sem redirecionar de volta ao formulário para
     * um segundo POST no cliente, que já causou um bug real de componente
     * Vue desmontado no meio do fluxo (a resposta de falha do primeiro
     * guard é uma navegação Inertia completa).
     *
     * @throws HttpResponseException quando as credenciais batem com um
     *                               paciente ativo — interrompe o pipeline
     *                               de autenticação do Fortify e devolve
     *                               o redirect para o portal.
     * @throws ValidationException quando o paciente existe mas está
     *                             inativo.
     */
    private function authenticatePatientOrFail(Request $request, string $email, string $password): void
    {
        $patientUser = PatientUser::query()->where('email', $email)->first();

        if (! $patientUser || ! Hash::check($password, $patientUser->password)) {
            return;
        }

        if (! $patientUser->is_active) {
            throw ValidationException::withMessages([
                Fortify::username() => 'Esta conta está desativada. Entre em contato com a clínica se precisar reativá-la.',
            ]);
        }

        Auth::guard('patient')->login($patientUser, $request->boolean('remember'));
        $request->session()->regenerate();

        throw new HttpResponseException(redirect()->route('patient-portal.dashboard'));
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
            // Atalho de redefinição direta (sem e-mail), exclusivo de
            // local/testing — ver DirectPasswordResetController.
            'directPasswordResetEnabled' => app()->environment(['local', 'testing']),
            'confirmedEmail' => $request->session()->get('confirmedEmail'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        RateLimiter::for('direct-password-reset', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // App\Http\Controllers\Auth\SendPasswordResetLinkController — "esqueci
        // minha senha" unificado de /login.
        RateLimiter::for('forgot-password-link', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
