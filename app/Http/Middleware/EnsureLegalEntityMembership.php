<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LegalEntity;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {legalEntity}: garante que a
 * entidade legal da URL pertence à organização ativa no contexto do
 * usuário — impede IDOR por substituição manual do ID na URL (ex.:
 * entidade legal de outra organização da qual o usuário também é dono).
 */
class EnsureLegalEntityMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeLegalEntity = $request->route('legalEntity');

        if ($routeLegalEntity instanceof LegalEntity) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeLegalEntity->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
