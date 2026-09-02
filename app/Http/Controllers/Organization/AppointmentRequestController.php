<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateAppointmentRequestNotesRequest;
use App\Http\Requests\Organization\UpdateAppointmentRequestStatusRequest;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administração dos leads de agendamento enviados pela landing pública.
 * Além de status/observação, admin e atendimento também podem confirmar o
 * pré-agendamento diretamente por aqui (mesmo endpoint de sempre,
 * `AppointmentController::store()`, gate `appointments.manage` — nunca
 * exclusivo do profissional dono, ver
 * App\Http\Controllers\Organization\MyAppointmentRequestsController para o
 * equivalente escopado ao próprio profissional).
 */
class AppointmentRequestController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('viewAny', [AppointmentRequest::class, $organization]);

        $professionalId = $request->string('professional_id')->value() ?: null;

        $requests = AppointmentRequest::query()
            ->with([
                'service:id,name',
                'professional:id,display_name',
                'unit:id,name',
                'preferredService:id,name',
                'patient:id,name,preferred_name',
                'appointment:id,status',
            ])
            ->where('organization_id', $organization?->id)
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            // Cancelado nunca some de verdade (histórico preservado), só some
            // da listagem padrão — quem quiser vê-lo escolhe "Cancelado" no
            // filtro de status explicitamente (achado de uso real: a tela
            // ficava poluída de leads já descartados).
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
                fn ($query) => $query->where('status', '!=', AppointmentRequestStatus::Cancelled),
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AppointmentRequest $request) => [
                'id' => $request->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'document' => $request->document,
                'service_name' => $request->service?->name,
                'preferred_period' => $request->preferred_period,
                'preferred_date' => $request->preferred_date?->toDateString(),
                'notes' => $request->notes,
                'internal_notes' => $request->internal_notes,
                'utm_data' => $request->utm_data,
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                'created_at' => $request->created_at?->toIso8601String(),
                'updated_at' => $request->updated_at?->toIso8601String(),
                // Status do Appointment de verdade, quando este lead já foi
                // convertido — igual MyAppointmentRequestsController::index().
                // É este campo, não `status`/`status_label`, que decide se a
                // ação de confirmar ainda deve aparecer (ver
                // InstantScheduleModal.vue / canConvert() no frontend).
                'appointment_status' => $request->appointment?->status->value,
                'appointment_status_label' => $request->appointment?->status->label(),
                'professional_id' => $request->professional_id,
                'professional_name' => $request->professional?->display_name,
                // Estruturados (unidade/serviço reais + horário exato) — só
                // presentes quando o lead veio de um horário específico
                // escolhido na busca de disponibilidade da landing. Mesmo
                // contrato de MyAppointmentRequestsController::index() —
                // ver InstantScheduleModal.vue.
                'unit_id' => $request->unit_id,
                'unit_name' => $request->unit?->name,
                'preferred_service_id' => $request->preferred_service_id,
                'preferred_service_name' => $request->preferredService?->name,
                'preferred_starts_at' => $request->preferred_starts_at?->toIso8601String(),
                'patient_id' => $request->patient_id,
                'patient_name' => $request->patient
                    ? ($request->patient->preferred_name ?: $request->patient->name)
                    : null,
            ]);

        return Inertia::render('settings/site/appointment-requests/Index', [
            'requests' => $requests,
            'professionals' => $organization?->professionals()
                ->where('status', RecordStatus::Active)
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
            'filters' => $request->only(['status', 'search', 'from', 'to', 'professional_id']),
            'can_create_appointments' => $organization !== null && Gate::allows('create', [Appointment::class, $organization]),
        ]);
    }

    public function updateStatus(
        UpdateAppointmentRequestStatusRequest $request,
        AppointmentRequest $appointmentRequest,
        AuditLogger $auditLogger,
        TenantContext $tenant,
    ): RedirectResponse {
        abort_unless($appointmentRequest->organization_id === $tenant->organization()?->id, 404);

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
        UpdateAppointmentRequestNotesRequest $request,
        AppointmentRequest $appointmentRequest,
        AuditLogger $auditLogger,
        TenantContext $tenant,
    ): RedirectResponse {
        abort_unless($appointmentRequest->organization_id === $tenant->organization()?->id, 404);

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
}
