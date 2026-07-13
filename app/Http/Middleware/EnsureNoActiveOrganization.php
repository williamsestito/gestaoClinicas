<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o onboarding de organização para quem já tem ao menos um
 * vínculo ativo com uma organização — impede reacesso ao assistente e
 * criação ilimitada de clínicas pela mesma rota (criação de uma nova
 * clínica adicional é um fluxo futuro e separado). Verifica todos os
 * vínculos do usuário diretamente (não apenas a organização ativa na
 * sessão), para cobrir também quem tem múltiplos vínculos sem contexto
 * resolvido.
 */
class EnsureNoActiveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasActiveMembership = $request->user()
            ?->organizationMemberships()
            ->where('status', OrganizationMembershipStatus::Active)
            ->exists() ?? false;

        if ($hasActiveMembership) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
