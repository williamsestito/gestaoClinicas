<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\LoginPatientUserRequest;
use App\Models\PatientUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login/logout do guard "patient" — sem Fortify (guard próprio, ver
 * docs/modules/patient-portal.md). Reproduz manualmente o padrão de
 * App\Providers\FortifyServiceProvider::authenticateUsing() (checa hash,
 * bloqueia conta inativa) porque não existe hook equivalente para um
 * segundo guard. Rate limit aplicado via middleware de rota
 * (throttle:patient-login, ver App\Providers\PatientPortalServiceProvider).
 */
class PatientAuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('patient-portal/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginPatientUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $patientUser = PatientUser::query()->where('email', $validated['email'])->first();

        if (! $patientUser || ! Hash::check($validated['password'], $patientUser->password)) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos.',
            ]);
        }

        if (! $patientUser->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Esta conta está desativada. Entre em contato com a clínica se precisar reativá-la.',
            ]);
        }

        // last_login_at é preenchido pelo listener genérico de Login em
        // App\Providers\AppServiceProvider::configureAuthEvents() — não é
        // filtrado por guard, então também cobre este login.
        Auth::guard('patient')->login($patientUser, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return to_route('patient-portal.dashboard');
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('patient')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return to_route('patient-portal.login');
    }
}
