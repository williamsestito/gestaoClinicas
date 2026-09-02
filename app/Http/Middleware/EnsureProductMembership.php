<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {product}: garante que o
 * produto da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsureProductMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeProduct = $request->route('product');

        if ($routeProduct instanceof Product) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeProduct->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
