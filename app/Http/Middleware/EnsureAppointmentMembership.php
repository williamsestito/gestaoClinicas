<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rotas com route model binding em {appointment}: garante que o
 * agendamento da URL pertence à organização ativa no contexto do usuário —
 * impede IDOR por substituição manual do ID na URL.
 */
class EnsureAppointmentMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeAppointment = $request->route('appointment');

        if ($routeAppointment instanceof Appointment) {
            $activeOrganization = app(TenantContext::class)->organization();

            if (! $activeOrganization || $routeAppointment->organization_id !== $activeOrganization->id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
