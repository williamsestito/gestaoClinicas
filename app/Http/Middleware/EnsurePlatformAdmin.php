<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe uma rota ao administrador da plataforma (`is_platform_admin`) —
 * usado no onboarding de organização, que deixou de ser autoatendimento
 * (decisão de negócio: toda nova organização representa uma cobrança
 * futura, então só nasce por ação deliberada do platform admin, nunca por
 * autocadastro público). Roda depois de `auth`, então um visitante não
 * autenticado já foi barrado antes de chegar aqui.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('web')?->is_platform_admin) {
            abort(403, 'Esta ação é restrita ao administrador da plataforma.');
        }

        return $next($request);
    }
}
