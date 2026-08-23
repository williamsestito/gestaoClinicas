<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Logout do guard "patient" — sem Fortify (guard próprio, ver
 * docs/modules/patient-portal.md). O login em si acontece em /login (ver
 * App\Providers\FortifyServiceProvider::configureActions()), que reconhece
 * paciente e staff pelo mesmo e-mail; não existe mais uma tela de login
 * dedicada ao portal.
 */
class PatientAuthenticatedSessionController extends Controller
{
    public function destroy(): RedirectResponse
    {
        Auth::guard('patient')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return to_route('login');
    }
}
