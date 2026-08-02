<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Specialty;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {specialty}: garante que a
 * especialidade da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsureSpecialtyMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeSpecialty = $request->route('specialty');

        if ($routeSpecialty instanceof Specialty) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeSpecialty->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
