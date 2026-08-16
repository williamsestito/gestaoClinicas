<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Rate limiters do portal do paciente — separados de
 * App\Providers\FortifyServiceProvider de propósito: o portal não usa
 * Fortify (guard próprio, ver config/auth.php e docs/modules/patient-portal.md),
 * mas o formato dos limiters espelha o que o Fortify já faz para staff.
 */
class PatientPortalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('patient-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('patient-register', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('patient-password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Escreve dados de Patient (perfil próprio ou novo dependente) —
        // sem isto, a checagem de unicidade de CPF vira um oráculo de
        // enumeração sem limite contra o cadastro real da clínica (achado
        // de security-review). Por conta autenticada, não por IP.
        RateLimiter::for('patient-portal-write', function (Request $request) {
            return Limit::perMinute(20)->by((string) $request->user('patient')?->id);
        });
    }
}
