<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateAppointmentRequestStatusRequest;
use App\Models\AppointmentRequest;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administração dos leads de agendamento enviados pela landing pública.
 * Apenas leitura + mudança de status — a confirmação real acontece fora do
 * sistema (telefone/WhatsApp), pois não existe agenda/disponibilidade real
 * ainda (ver App\Models\AppointmentRequest).
 */
class AppointmentRequestController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('viewAny', [AppointmentRequest::class, $organization]);

        $requests = AppointmentRequest::query()
            ->with('service:id,name')
            ->where('organization_id', $organization?->id)
            ->latest()
            ->get()
            ->map(fn (AppointmentRequest $request) => [
                'id' => $request->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'service_name' => $request->service?->name,
                'preferred_period' => $request->preferred_period,
                'notes' => $request->notes,
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                'created_at' => $request->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/site/appointment-requests/Index', [
            'requests' => $requests,
        ]);
    }

    public function updateStatus(
        UpdateAppointmentRequestStatusRequest $request,
        AppointmentRequest $appointmentRequest,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $before = $appointmentRequest->only(['status']);
        $appointmentRequest->update(['status' => $request->validated('status')]);

        $auditLogger->log(
            AuditAction::Updated,
            auditable: $appointmentRequest,
            before: $before,
            after: $appointmentRequest->only(['status']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status atualizado.']);

        return back();
    }
}
