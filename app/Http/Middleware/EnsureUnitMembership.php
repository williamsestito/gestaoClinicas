<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RecordStatus;
use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {unit}. Não basta a unidade
 * pertencer à organização ativa: valida também que o usuário autenticado
 * possui vínculo (UnitMembership) ativo com essa unidade específica e que a
 * unidade em si está ativa — impede IDOR por substituição manual do ID na
 * URL (unidade de outra organização, unidade sem vínculo, unidade inativa).
 */
class EnsureUnitMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeUnit = $request->route('unit');

        if ($routeUnit instanceof Unit) {
            $tenant = app(TenantContext::class);
            $activeOrganization = $tenant->organization();
            $membership = $tenant->membership();

            if (! $activeOrganization || ! $membership || $routeUnit->organization_id !== $activeOrganization->id) {
                abort(404);
            }

            if ($routeUnit->status !== RecordStatus::Active) {
                abort(403, 'Esta unidade não está ativa no momento.');
            }

            $hasActiveUnitAccess = $membership->unitMemberships()
                ->where('unit_id', $routeUnit->id)
                ->where('status', RecordStatus::Active)
                ->exists();

            if (! $hasActiveUnitAccess) {
                abort(403, 'Você não tem acesso a esta unidade.');
            }
        }

        return $next($request);
    }
}
