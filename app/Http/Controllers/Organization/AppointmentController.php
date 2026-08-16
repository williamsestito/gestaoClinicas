<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CancelAppointmentAction;
use App\Actions\Organization\CheckInAppointmentAction;
use App\Actions\Organization\CompleteAppointmentAction;
use App\Actions\Organization\CreateAppointmentAction;
use App\Actions\Organization\MarkAppointmentNoShowAction;
use App\Actions\Organization\RescheduleAppointmentAction;
use App\Actions\Organization\StartAppointmentAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CancelAppointmentRequest;
use App\Http\Requests\Organization\CreateAppointmentRequest;
use App\Http\Requests\Organization\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\Service;
use App\Models\Unit;
use App\Services\Availability\StaffAppointmentSlotFinder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('viewAny', [Appointment::class, $organization]);

        $date = $request->filled('date') ? Carbon::parse($request->string('date')->value()) : Carbon::today();
        $professionalId = $request->string('professional_id')->value() ?: null;

        $appointments = $organization->appointments()
            ->with(['professional:id,display_name', 'patient:id,name,preferred_name', 'service:id,name', 'unit:id,name'])
            ->whereDate('starts_at', $date->toDateString())
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'professional_name' => $appointment->professional->display_name,
                'patient_name' => $appointment->patient->preferred_name ?: $appointment->patient->name,
                'service_name' => $appointment->service->name,
                'unit_name' => $appointment->unit->name,
                'cancellation_reason' => $appointment->cancellation_reason,
            ]);

        $professionals = $organization->professionals()
            ->where('status', RecordStatus::Active)
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return Inertia::render('settings/appointments/Index', [
            'appointments' => $appointments,
            'professionals' => $professionals,
            'filters' => ['date' => $date->toDateString(), 'professional_id' => $professionalId],
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('create', [Appointment::class, $organization]);

        return Inertia::render('settings/appointments/Create', [
            'units' => $organization->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'professionals' => $organization->professionals()->where('status', RecordStatus::Active)->orderBy('display_name')->get(['id', 'display_name']),
            'services' => $organization->services()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CreateAppointmentRequest $request, CreateAppointmentAction $action, TenantContext $tenant): RedirectResponse
    {
        $organization = $tenant->organization();
        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('unit_id'));
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('professional_id'));
        $patient = Patient::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('patient_id'));
        $service = Service::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('service_id'));

        $link = $this->resolveActiveLink($organization->id, $professional->id, $service->id, $unit->id);

        // O horário enviado é a hora civil local da unidade (mesmo valor
        // devolvido por StaffAppointmentSlotFinder), nunca o fuso padrão da
        // aplicação (UTC). ->utc() explícito é obrigatório: o cast
        // 'datetime' do Eloquent serializa o Carbon no fuso em que ele já
        // está, sem converter sozinho (mesma disciplina de
        // App\Support\Availability\LocalTimeConverter::toUtc()).
        $startsAt = Carbon::parse($request->validated('starts_at'), $unit->timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($link->effectiveDurationMinutes());

        $action->handle($organization, $unit, $professional, $patient, $service, $startsAt, $endsAt, $request->validated('notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento criado com sucesso.']);

        return to_route('settings.appointments.index');
    }

    public function availableSlots(Request $request, TenantContext $tenant, StaffAppointmentSlotFinder $finder): JsonResponse
    {
        $organization = $tenant->organization();
        $this->authorize('create', [Appointment::class, $organization]);

        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail($request->string('unit_id')->value());
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail($request->string('professional_id')->value());
        $service = Service::query()->where('organization_id', $organization->id)->findOrFail($request->string('service_id')->value());
        $date = Carbon::parse($request->string('date')->value());

        $link = $this->resolveActiveLink($organization->id, $professional->id, $service->id, $unit->id);

        return response()->json([
            'slots' => $finder->availableTimes($professional, $unit, $link, $date)->values(),
        ]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment, RescheduleAppointmentAction $action): RedirectResponse
    {
        $duration = (int) $appointment->starts_at->diffInMinutes($appointment->ends_at);
        $startsAt = Carbon::parse($request->validated('starts_at'), $appointment->unit->timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $action->handle($appointment, $startsAt, $endsAt);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento reagendado com sucesso.']);

        return back();
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment, CancelAppointmentAction $action): RedirectResponse
    {
        $action->handle($appointment, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento cancelado.']);

        return back();
    }

    public function checkIn(Appointment $appointment, CheckInAppointmentAction $action): RedirectResponse
    {
        $this->authorize('manageStatus', $appointment);

        $action->handle($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Check-in realizado.']);

        return back();
    }

    public function start(Appointment $appointment, StartAppointmentAction $action): RedirectResponse
    {
        $this->authorize('manageStatus', $appointment);

        $action->handle($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Atendimento iniciado.']);

        return back();
    }

    public function complete(Appointment $appointment, CompleteAppointmentAction $action): RedirectResponse
    {
        $this->authorize('manageStatus', $appointment);

        $action->handle($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Atendimento concluído.']);

        return back();
    }

    public function noShow(Appointment $appointment, MarkAppointmentNoShowAction $action): RedirectResponse
    {
        $this->authorize('manageStatus', $appointment);

        $action->handle($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento marcado como não comparecido.']);

        return back();
    }

    private function resolveActiveLink(string $organizationId, string $professionalId, string $serviceId, string $unitId): ProfessionalService
    {
        $link = ProfessionalService::query()
            ->where('organization_id', $organizationId)
            ->where('professional_id', $professionalId)
            ->where('service_id', $serviceId)
            ->where('status', RecordStatus::Active)
            ->first();

        if (! $link || ! $link->compatibleUnitIds()->contains($unitId)) {
            throw ValidationException::withMessages([
                'service_id' => 'Este profissional não executa o serviço selecionado nesta unidade.',
            ]);
        }

        return $link;
    }
}
