<?php

use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Middleware\EnsureActiveUnit;
use App\Http\Middleware\EnsureLegalEntityMembership;
use App\Http\Middleware\EnsureNoActiveOrganization;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\EnsureUnitMembership;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Http\Middleware\ResolveUnitContext;
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

        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.organization' => ResolveOrganizationContext::class,
            'tenant.unit' => ResolveUnitContext::class,
            'tenant.active-organization' => EnsureActiveOrganization::class,
            'tenant.active-unit' => EnsureActiveUnit::class,
            'tenant.no-active-organization' => EnsureNoActiveOrganization::class,
            'tenant.organization-membership' => EnsureOrganizationMembership::class,
            'tenant.unit-membership' => EnsureUnitMembership::class,
            'tenant.legal-entity-membership' => EnsureLegalEntityMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
