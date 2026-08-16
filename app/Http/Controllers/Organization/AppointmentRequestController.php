<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\AuditAction;
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
 * Apenas leitura + mudança de status/observação — a confirmação real
 * acontece fora do sistema (telefone/WhatsApp), pois não existe
 * agenda/disponibilidade real ainda (ver App\Models\AppointmentRequest).
 */
class AppointmentRequestController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('viewAny', [AppointmentRequest::class, $organization]);

        $requests = AppointmentRequest::query()
            ->with('service:id,name')
            ->where('organization_id', $organization?->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
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
            ]);

        return Inertia::render('settings/site/appointment-requests/Index', [
            'requests' => $requests,
            'filters' => $request->only(['status', 'search', 'from', 'to']),
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
