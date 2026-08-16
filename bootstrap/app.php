<?php

use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Middleware\EnsureActiveUnit;
use App\Http\Middleware\EnsureAppointmentMembership;
use App\Http\Middleware\EnsureLegalEntityMembership;
use App\Http\Middleware\EnsureNoActiveOrganization;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\EnsurePatientMembership;
use App\Http\Middleware\EnsurePatientUserIsActive;
use App\Http\Middleware\EnsureProfessionalMembership;
use App\Http\Middleware\EnsureServiceMembership;
use App\Http\Middleware\EnsureSpecialtyMembership;
use App\Http\Middleware\EnsureUnitMembership;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Http\Middleware\ResolveUnitContext;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SharePatientPortalData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Proxies confiáveis (load balancer/edge em produção) para que
        // Request::ip() e a URL gerada (esquema/host) reflitam a origem
        // real, não o proxy. Vazio por padrão (nada é confiável) — nunca
        // muda o comportamento de instalações que não definem a variável.
        // "*" confia em qualquer proxy (usual atrás de um edge gerenciado,
        // ex.: Laravel Cloud); uma lista de IPs/CIDRs é mais restritiva.
        // env() (não config()) é intencional aqui: o binding 'config' do
        // container ainda não existe neste ponto do boot (withMiddleware
        // roda antes de LoadConfiguration) — config() lançaria
        // BindingResolutionException. bootstrap/app.php nunca se beneficia
        // de config:cache de qualquer forma (roda a cada boot, cache ou
        // não), então isso não sofre o problema que a regra do Larastan
        // normalmente previne (ver phpstan.neon).
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        $middleware->trustProxies(
            at: match (true) {
                $trustedProxies === '' => [],
                $trustedProxies === '*' => '*',
                default => array_map('trim', explode(',', $trustedProxies)),
            },
        );

        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        // Sem isto, o middleware "auth:patient" redirecionaria um visitante
        // não autenticado para a tela de login de staff ("login") em vez da
        // tela de login do portal — o redirect padrão do Laravel não é
        // guard-aware.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('portal*')
                ? route('patient-portal.login')
                : route('login'),
        );

        $middleware->alias([
            'tenant.organization' => ResolveOrganizationContext::class,
            'tenant.unit' => ResolveUnitContext::class,
            'tenant.active-organization' => EnsureActiveOrganization::class,
            'tenant.active-unit' => EnsureActiveUnit::class,
            'tenant.no-active-organization' => EnsureNoActiveOrganization::class,
            'tenant.organization-membership' => EnsureOrganizationMembership::class,
            'tenant.unit-membership' => EnsureUnitMembership::class,
            'tenant.legal-entity-membership' => EnsureLegalEntityMembership::class,
            'tenant.specialty-membership' => EnsureSpecialtyMembership::class,
            'tenant.service-membership' => EnsureServiceMembership::class,
            'tenant.patient-membership' => EnsurePatientMembership::class,
            'tenant.appointment-membership' => EnsureAppointmentMembership::class,
            'tenant.professional-membership' => EnsureProfessionalMembership::class,
            'patient.active' => EnsurePatientUserIsActive::class,
            'patient.share-portal-data' => SharePatientPortalData::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
