<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RecordStatus;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a unidade ativa da sessão para o TenantContext, restrita às
 * unidades da organização ativa às quais o usuário tem vínculo. Deve rodar
 * depois de ResolveOrganizationContext no pipeline de middlewares.
 */
class ResolveUnitContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class);
        $membership = $tenant->membership();

        if (! $membership) {
            return $next($request);
        }

        $unitMemberships = $membership->unitMemberships()
            ->with('unit')
            ->where('status', RecordStatus::Active)
            ->get();

        $activeUnitId = $request->session()->get('active_unit_id');
        $unitMembership = $activeUnitId
            ? $unitMemberships->firstWhere('unit_id', $activeUnitId)
            : null;

        if ($activeUnitId && ! $unitMembership) {
            $request->session()->forget('active_unit_id');
        }

        if (! $unitMembership && $unitMemberships->count() === 1) {
            $unitMembership = $unitMemberships->first();
            $request->session()->put('active_unit_id', $unitMembership->unit_id);
        }

        $tenant->setUnit($unitMembership?->unit, $unitMembership);

        return $next($request);
    }
}
