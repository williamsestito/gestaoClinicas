<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {unit}: garante que a unidade da
 * URL pertence à organização ativa no contexto do usuário — impede IDOR por
 * substituição manual do ID na URL (ex.: unidade de outra organização).
 */
class EnsureUnitMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeUnit = $request->route('unit');

        if ($routeUnit instanceof Unit) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeUnit->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
