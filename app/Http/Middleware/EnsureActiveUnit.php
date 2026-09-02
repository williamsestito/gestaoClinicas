<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RecordStatus;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia rotas operacionais (vinculadas a uma unidade) quando não há
 * unidade ativa resolvida (ver ResolveUnitContext) ou quando a unidade ativa
 * não está mais ativa/válida — limpa o contexto inválido e redireciona para
 * a seleção de unidade. Deve rodar depois de ResolveUnitContext.
 */
class EnsureActiveUnit
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class);

        if (! $tenant->hasUnit() || $tenant->unit()->status !== RecordStatus::Active) {
            $request->session()->forget('active_unit_id');

            return redirect()->route('context.unit.edit');
        }

        return $next($request);
    }
}
