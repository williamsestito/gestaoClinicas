<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
            $user = $request->user('web');

            // Instalação single-tenant (ADR-010): a plataforma nunca tem
            // "várias organizações para escolher" de verdade, só a única
            // organização desta instalação — que ainda pode não existir
            // (banco recém-migrado). Nesse caso o platform admin vai para o
            // painel administrativo (Filament, /admin), de onde cria a
            // primeira organização; só quando já existe alguma organização
            // é que faz sentido levá-lo ao seletor.
            //
            // /admin é o painel Filament — HTML convencional, sem o
            // protocolo Inertia. Esta rota é sempre visitada via Inertia
            // (SPA), então um redirect() comum vira 302 seguido pelo próprio
            // cliente Inertia, que tenta processar a resposta HTML do
            // Filament como se fosse uma página da SPA (tela branca).
            // Inertia::location() resolve os dois casos sozinho: 409 +
            // X-Inertia-Location (o cliente faz um window.location real)
            // quando a requisição atual é uma visita Inertia, ou um redirect
            // comum quando não é (primeiro carregamento de página) — ver
            // vendor/inertiajs/inertia-laravel/src/ResponseFactory.php.
            if ($user->is_platform_admin) {
                return Organization::query()->exists()
                    ? redirect()->route('context.organization.edit')
                    : Inertia::location('/admin');
            }

            $hasAnyMembership = $user
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
