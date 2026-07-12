<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {organization}: garante que o
 * ULID da URL corresponde à organização realmente ativa no contexto do
 * usuário (nunca confia apenas no binding do Eloquent) — impede IDOR por
 * substituição manual do ID na URL.
 */
class EnsureOrganizationMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeOrganization = $request->route('organization');

        if ($routeOrganization instanceof Organization) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeOrganization->id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
