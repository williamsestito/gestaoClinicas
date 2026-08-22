<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOwnAppointmentRequestNotesRequest;
use App\Http\Requests\Organization\UpdateOwnAppointmentRequestStatusRequest;
use App\Models\AppointmentRequest;
use App\Models\Professional;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Meus pré-agendamentos" — nunca aceita um `professional_id` do frontend
 * para a listagem: o profissional é sempre resolvido a partir do usuário
 * autenticado, mesmo padrão de MyPatientsController/MyScheduleController.
 * `updateStatus()`/`updateNotes()` validam o vínculo diretamente no
 * FormRequest (ver UpdateOwnAppointmentRequestStatusRequest) — nunca
 * reaproveitam `AppointmentRequestPolicy` (que autoriza por
 * `SiteAppointmentsManage`, permissão administrativa de toda a
 * organização, não por vínculo com um profissional específico).
 */
class MyAppointmentRequestsController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $professional = $organization ? $this->resolveOwnProfessional($organization->id) : null;

        if ($organization === null || $professional === null) {
            return Inertia::render('settings/my-appointment-requests/Index', ['requests' => null]);
        }

        $requests = AppointmentRequest::query()
            ->with(['service:id,name', 'appointment:id,status'])
            ->where('organization_id', $organization->id)
            ->where('professional_id', $professional->id)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AppointmentRequest $request) => [
                'id' => $request->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'service_name' => $request->service?->name,
                'preferred_period' => $request->preferred_period,
                'preferred_date' => $request->preferred_date?->toDateString(),
                'notes' => $request->notes,
                'internal_notes' => $request->internal_notes,
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                // Status do Appointment de verdade, quando este lead já foi
                // convertido (ver docs/modules/public-integration.md) — lido
                // direto do registro vinculado, então reflete qualquer
                // confirmação/check-in/conclusão feita pelo profissional
                // sem nenhuma sincronização adicional daqui.
                'appointment_status' => $request->appointment?->status->value,
                'appointment_status_label' => $request->appointment?->status->label(),
                'created_at' => $request->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/my-appointment-requests/Index', [
            'requests' => $requests,
        ]);
    }

    public function updateStatus(
        UpdateOwnAppointmentRequestStatusRequest $request,
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

    public function updateNotes(
        UpdateOwnAppointmentRequestNotesRequest $request,
        AppointmentRequest $appointmentRequest,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $before = $appointmentRequest->only(['internal_notes']);
        $appointmentRequest->update(['internal_notes' => $request->validated('internal_notes')]);

        $auditLogger->log(
            AuditAction::Updated,
            auditable: $appointmentRequest,
            before: $before,
            after: $appointmentRequest->only(['internal_notes']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Observação salva.']);

        return back();
    }

    private function resolveOwnProfessional(string $organizationId): ?Professional
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->professionals()
            ->where('organization_id', $organizationId)
            ->where('status', RecordStatus::Active)
            ->first();
    }
}
