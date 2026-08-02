<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Service;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {service}: garante que o
 * serviço da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsureServiceMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeService = $request->route('service');

        if ($routeService instanceof Service) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeService->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
