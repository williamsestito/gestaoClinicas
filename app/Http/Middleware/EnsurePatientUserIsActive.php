<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalente de EnsureUserIsActive, mas para o guard "patient" — bloqueia
 * qualquer requisição autenticada de uma conta de portal com is_active =
 * false. Aplicado só dentro do grupo autenticado de
 * routes/patient-portal.php (nunca no grupo "web" global, que é
 * guard-agnóstico e não deve saber de particularidades do portal).
 */
class EnsurePatientUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $patientUser = Auth::guard('patient')->user();

        if ($patientUser && ! $patientUser->is_active) {
            Auth::guard('patient')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('patient-portal.login')
                ->with('status', 'Esta conta está desativada. Entre em contato com a clínica se precisar reativá-la.');
        }

        return $next($request);
    }
}
