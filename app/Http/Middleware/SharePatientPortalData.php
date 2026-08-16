<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compartilha a conta autenticada do portal (guard "patient") como prop
 * Inertia — só dentro do grupo autenticado de routes/patient-portal.php.
 * App\Http\Middleware\HandleInertiaRequests é staff-oriented (auth.user/
 * tenant resolvidos contra o guard "web") e não deve saber de
 * particularidades do portal; esta middleware roda depois dela e apenas
 * complementa a chave "auth".
 */
class SharePatientPortalData
{
    public function handle(Request $request, Closure $next): Response
    {
        $patientUser = Auth::guard('patient')->user();

        Inertia::share('auth', [
            'user' => null,
            'patientUser' => $patientUser?->only(['id', 'name', 'email']),
        ]);

        return $next($request);
    }
}
