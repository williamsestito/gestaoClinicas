<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MedicalRecord;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mesmo padrão de EnsureAppointmentMembership: protege rotas com route
 * model binding em {medicalRecord}, garantindo que o prontuário pertence à
 * organização ativa no contexto do usuário — um usuário pode ter vínculo
 * profissional ativo em mais de uma organização, então checar isso aqui
 * (contra a organização ATIVA na sessão) é diferente e complementar ao que
 * App\Policies\MedicalRecordPolicy já checa (vínculo de profissional
 * qualquer que seja a organização).
 */
class EnsureMedicalRecordMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeMedicalRecord = $request->route('medicalRecord');

        if ($routeMedicalRecord instanceof MedicalRecord) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeMedicalRecord->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
