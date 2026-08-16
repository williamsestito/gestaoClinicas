<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Patient;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {patient}: garante que o
 * paciente da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsurePatientMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routePatient = $request->route('patient');

        if ($routePatient instanceof Patient) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routePatient->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
