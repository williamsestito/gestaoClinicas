<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {sale}: garante que a venda da
 * URL pertence à organização ativa no contexto do usuário — impede IDOR
 * por substituição manual do ID na URL.
 */
class EnsureSaleMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeSale = $request->route('sale');

        if ($routeSale instanceof Sale) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeSale->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
