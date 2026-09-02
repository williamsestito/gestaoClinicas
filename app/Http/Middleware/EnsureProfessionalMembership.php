<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Professional;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {professional}: garante que o
 * profissional da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsureProfessionalMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeProfessional = $request->route('professional');

        if ($routeProfessional instanceof Professional) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeProfessional->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
