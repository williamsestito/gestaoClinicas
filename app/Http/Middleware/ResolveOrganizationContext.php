<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a organização ativa da sessão para o TenantContext, validando
 * sempre contra os vínculos reais do usuário autenticado (nunca confia
 * apenas no ID guardado na sessão). Não bloqueia a requisição por si só —
 * ver EnsureActiveOrganization para o bloqueio.
 */
class ResolveOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user) {
            return $next($request);
        }

        $memberships = $user->organizationMemberships()
            ->with('organization')
            ->where('status', OrganizationMembershipStatus::Active)
            ->get();

        $activeOrganizationId = $request->session()->get('active_organization_id');
        $membership = $activeOrganizationId
            ? $memberships->firstWhere('organization_id', $activeOrganizationId)
            : null;

        if ($activeOrganizationId && ! $membership) {
            // ID salvo na sessão não corresponde (mais) a um vínculo ativo real.
            $request->session()->forget(['active_organization_id', 'active_unit_id']);
        }

        if (! $membership && $memberships->count() === 1) {
            $membership = $memberships->first();
            $request->session()->put('active_organization_id', $membership->organization_id);
        }

        app(TenantContext::class)->setOrganization(
            $membership?->organization,
            $membership,
        );

        return $next($request);
    }
}
