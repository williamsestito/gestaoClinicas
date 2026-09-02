<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o acesso à área da clínica quando não há organização ativa
 * resolvida (ver ResolveOrganizationContext) ou quando a organização ativa
 * está suspensa/inativa. Deve rodar depois de ResolveOrganizationContext.
 */
class EnsureActiveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class);

        if (! $tenant->hasOrganization()) {
            // Superadmin nunca é levado para "criar uma clínica nova" — ele
            // sempre tem organizações existentes para escolher (acesso
            // global), mesmo sem vínculo ainda em nenhuma.
            $hasAnyMembership = $request->user('web')->is_platform_admin
                || $request->user('web')
                    ->organizationMemberships()
                    ->where('status', OrganizationMembershipStatus::Active)
                    ->exists();

            return redirect()->route(
                $hasAnyMembership ? 'context.organization.edit' : 'onboarding.organization.create',
            );
        }

        if ($tenant->organization()->status !== OrganizationStatus::Active) {
            abort(403, 'Esta organização não está ativa no momento.');
        }

        return $next($request);
    }
}
