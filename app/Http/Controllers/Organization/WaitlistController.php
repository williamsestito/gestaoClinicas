<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CancelWaitlistEntryAction;
use App\Actions\Organization\CreateWaitlistEntryAction;
use App\Enums\RecordStatus;
use App\Enums\WaitlistEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateWaitlistEntryRequest;
use App\Models\Appointment;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lista de espera (Etapa 3.3 do roadmap) — reaproveita a permissão
 * `appointments.manage` (via App\Policies\AppointmentPolicy::create()),
 * não é uma permissão nova: faz parte do mesmo fluxo de agenda de quem já
 * cria/confirma agendamento.
 */
class WaitlistController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('viewAny', [Appointment::class, $organization]);

        $entries = WaitlistEntry::query()
            ->where('organization_id', $organization->id)
            ->where('status', WaitlistEntryStatus::Waiting)
            ->with(['unit:id,name', 'professional:id,display_name', 'service:id,name', 'patient:id,name,preferred_name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (WaitlistEntry $entry) => [
                'id' => $entry->id,
                'unit_name' => $entry->unit->name,
                'professional_name' => $entry->professional?->display_name,
                'service_id' => $entry->service_id,
                'service_name' => $entry->service->name,
                'patient_name' => $entry->patient->preferred_name ?: $entry->patient->name,
                'preferred_date' => $entry->preferred_date?->toDateString(),
                'notes' => $entry->notes,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/appointments/waitlist/Index', [
            'entries' => $entries,
            'units' => $organization->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'professionals' => $organization->professionals()->where('status', RecordStatus::Active)->orderBy('display_name')->get(['id', 'display_name']),
            'services' => $organization->services()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CreateWaitlistEntryRequest $request, CreateWaitlistEntryAction $action, TenantContext $tenant): RedirectResponse
    {
        $organization = $tenant->organization();
        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('unit_id'));

        $action->handle($organization, $unit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente adicionado à lista de espera.']);

        return back();
    }

    public function cancel(WaitlistEntry $waitlistEntry, CancelWaitlistEntryAction $action, TenantContext $tenant): RedirectResponse
    {
        abort_unless($waitlistEntry->organization_id === $tenant->organization()?->id, 404);
        $this->authorize('create', [Appointment::class, $tenant->organization()]);

        $action->handle($waitlistEntry);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrada removida da lista de espera.']);

        return back();
    }
}
