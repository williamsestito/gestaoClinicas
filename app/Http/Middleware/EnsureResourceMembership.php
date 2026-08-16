<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SharedResource;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {resource}: garante que o
 * recurso da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsureResourceMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeResource = $request->route('resource');

        if ($routeResource instanceof SharedResource) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeResource->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
