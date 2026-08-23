<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CancelAppointmentAction;
use App\Actions\Organization\CheckInAppointmentAction;
use App\Actions\Organization\CompleteAppointmentAction;
use App\Actions\Organization\ConfirmAppointmentAction;
use App\Actions\Organization\CreateAppointmentAction;
use App\Actions\Organization\CreateRecurringAppointmentSeriesAction;
use App\Actions\Organization\MarkAppointmentNoShowAction;
use App\Actions\Organization\ProposeAlternateTimeAction;
use App\Actions\Organization\RescheduleAppointmentAction;
use App\Actions\Organization\StartAppointmentAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CancelAppointmentRequest;
use App\Http\Requests\Organization\CreateAppointmentRequest;
use App\Http\Requests\Organization\ProposeAlternateTimeRequest;
use App\Http\Requests\Organization\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use App\Services\Availability\StaffAppointmentSlotFinder;
use App\Support\Availability\ActiveProfessionalServiceLinkResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(private readonly ActiveProfessionalServiceLinkResolver $linkResolver) {}

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
                'is_recurring' => $appointment->recurrence_group_id !== null,
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

    public function create(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $appointmentRequestId = $request->string('appointment_request_id')->value() ?: null;
        $sourceRequest = $appointmentRequestId
            ? AppointmentRequest::query()->where('organization_id', $organization->id)->findOrFail($appointmentRequestId)
            : null;

        // Quem tem `appointments.manage` sempre passa por `create()`; um
        // profissional só chega aqui convertendo o PRÓPRIO pré-agendamento
        // (ver AppointmentPolicy::createFromOwnRequest()).
        if ($sourceRequest === null || ! $request->user()->can('createFromOwnRequest', $sourceRequest)) {
            $this->authorize('create', [Appointment::class, $organization]);
        }

        $waitlistEntryId = $request->string('waitlist_entry_id')->value() ?: null;
        $sourceWaitlistEntry = $waitlistEntryId
            ? WaitlistEntry::query()->where('organization_id', $organization->id)->with('patient')->findOrFail($waitlistEntryId)
            : null;

        $prefill = null;
        if ($sourceRequest) {
            $prefill = [
                'appointment_request_id' => $sourceRequest->id,
                'name' => $sourceRequest->name,
                'phone' => $sourceRequest->phone,
                'notes' => $sourceRequest->notes,
                // Unidade e profissional já são conhecidos quando a
                // solicitação já os carrega — trava esses dois campos no
                // formulário (Create.vue) em vez de fazer quem está
                // convertendo reescolher o que já está decidido. Serviço e
                // horário exatos nunca vêm daqui: o serviço da solicitação é
                // o catálogo público (SiteService), espaço de id diferente
                // do serviço operacional exigido aqui, e só existe uma data/
                // período aproximados, nunca um horário exato.
                'unit_id' => $sourceRequest->unit_id,
                'professional_id' => $sourceRequest->professional_id,
            ];
        } elseif ($sourceWaitlistEntry) {
            $prefill = [
                'waitlist_entry_id' => $sourceWaitlistEntry->id,
                'name' => $sourceWaitlistEntry->patient->preferred_name ?: $sourceWaitlistEntry->patient->name,
                'phone' => $sourceWaitlistEntry->patient->phone ?? '',
                'notes' => $sourceWaitlistEntry->notes,
            ];
        }

        return Inertia::render('settings/appointments/Create', [
            'units' => $organization->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'professionals' => $organization->professionals()->where('status', RecordStatus::Active)->orderBy('display_name')->get(['id', 'display_name']),
            'services' => $organization->services()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'resources' => $organization->resources()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'unit_id', 'name']),
            'prefill' => $prefill,
        ]);
    }

    public function store(
        CreateAppointmentRequest $request,
        CreateAppointmentAction $action,
        CreateRecurringAppointmentSeriesAction $recurringAction,
        TenantContext $tenant,
    ): RedirectResponse {
        $organization = $tenant->organization();
        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('unit_id'));
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('professional_id'));
        $patient = Patient::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('patient_id'));
        $service = Service::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('service_id'));

        $link = $this->linkResolver->resolve($organization->id, $professional->id, $service->id, $unit->id);

        $sourceRequestId = $request->validated('appointment_request_id');
        $sourceRequest = $sourceRequestId
            ? AppointmentRequest::query()->where('organization_id', $organization->id)->findOrFail((string) $sourceRequestId)
            : null;

        // CreateAppointmentRequest::authorize() já aceitou este envio por um
        // dos dois caminhos: `appointments.manage` (qualquer profissional) ou
        // "converter o próprio pré-agendamento" (createFromOwnRequest). Neste
        // segundo caso, travar aqui dois pontos que a autorização sozinha não
        // cobre — achado de security-review:
        // 1. `professional_id` enviado precisa ser realmente o do próprio
        //    vínculo — sem isso, um profissional autorizado só pela
        //    conversão própria poderia trocar o `professional_id` do
        //    formulário e criar o agendamento na agenda de outro
        //    profissional.
        // 2. `patient_id` precisa ser o mesmo já vinculado ao pré-agendamento
        //    de origem OU um paciente cujo `primary_professional_id` já é o
        //    do próprio profissional (mesma definição de "paciente próprio"
        //    de PatientPolicy::hasOwnAccess()) — sem isso, `AppointmentsManageOwn`
        //    (que não inclui `patients.manage`/`patients.view`) permitia
        //    criar um agendamento real vinculando qualquer paciente da
        //    organização, não só os do próprio profissional.
        // `unit_id`/`service_id` já ficam implicitamente restritos ao que o
        // profissional realmente atende por `ActiveProfessionalServiceLinkResolver`
        // acima, que rejeita qualquer combinação sem vínculo ativo.
        if ($sourceRequest !== null && ! $request->user()->can('create', [Appointment::class, $organization])) {
            abort_unless($sourceRequest->professional_id === $professional->id, 403);
            abort_unless(
                $patient->id === $sourceRequest->patient_id || $patient->primary_professional_id === $professional->id,
                403,
            );
        }

        $sessionPackageId = $request->validated('session_package_id');
        $sessionPackage = $sessionPackageId
            ? SessionPackage::query()->where('organization_id', $organization->id)->findOrFail((string) $sessionPackageId)
            : null;

        $waitlistEntryId = $request->validated('waitlist_entry_id');
        $sourceWaitlistEntry = $waitlistEntryId
            ? WaitlistEntry::query()->where('organization_id', $organization->id)->findOrFail((string) $waitlistEntryId)
            : null;

        // O horário enviado é a hora civil local da unidade (mesmo valor
        // devolvido por StaffAppointmentSlotFinder), nunca o fuso padrão da
        // aplicação (UTC). ->utc() explícito é obrigatório: o cast
        // 'datetime' do Eloquent serializa o Carbon no fuso em que ele já
        // está, sem converter sozinho (mesma disciplina de
        // App\Support\Availability\LocalTimeConverter::toUtc()).
        $startsAt = Carbon::parse($request->validated('starts_at'), $unit->timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($link->effectiveDurationMinutes());

        $recurrenceWeeks = $request->validated('recurrence_weeks');

        if ($recurrenceWeeks) {
            $result = $recurringAction->handle(
                $organization,
                $unit,
                $professional,
                $patient,
                $service,
                $startsAt,
                $endsAt,
                (int) $recurrenceWeeks,
                $request->validated('notes'),
                $sourceRequest,
                $request->validated('resource_ids') ?? [],
                $sessionPackage,
            );

            $createdCount = count($result['created']);
            $skippedCount = count($result['skipped']);
            $message = $skippedCount === 0
                ? "{$createdCount} agendamentos criados com sucesso."
                : "{$createdCount} de {$recurrenceWeeks} agendamentos criados. {$skippedCount} data(s) tiveram conflito e foram puladas.";

            Inertia::flash('toast', ['type' => $skippedCount === 0 ? 'success' : 'info', 'message' => $message]);

            return to_route('settings.appointments.index');
        }

        $action->handle(
            $organization,
            $unit,
            $professional,
            $patient,
            $service,
            $startsAt,
            $endsAt,
            $request->validated('notes'),
            $sourceRequest,
            $request->validated('resource_ids') ?? [],
            $sessionPackage,
            recurrenceGroupId: null,
            sourceWaitlistEntry: $sourceWaitlistEntry,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento criado com sucesso.']);

        return to_route('settings.appointments.index');
    }

    public function patientSessionPackages(Patient $patient, TenantContext $tenant): JsonResponse
    {
        $organization = $tenant->organization();
        abort_unless($organization && $patient->organization_id === $organization->id, 404);
        $this->authorize('create', [Appointment::class, $organization]);

        $packages = $patient->sessionPackages()
            ->with('service:id,name')
            ->where('status', RecordStatus::Active)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString()))
            ->get()
            ->map(fn (SessionPackage $package) => [
                'id' => $package->id,
                'service_id' => $package->service_id,
                'service_name' => $package->service?->name,
                'remaining_sessions' => $package->remainingSessions(),
            ])
            ->filter(fn (array $package) => $package['remaining_sessions'] > 0)
            ->values();

        return response()->json(['packages' => $packages]);
    }

    public function availableSlots(Request $request, TenantContext $tenant, StaffAppointmentSlotFinder $finder): JsonResponse
    {
        $organization = $tenant->organization();

        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail($request->string('professional_id')->value());

        // Mesmo racional de create()/store(): quem tem `appointments.manage`
        // sempre autoriza; um profissional sem essa permissão só consulta a
        // própria disponibilidade (convertendo o próprio pré-agendamento). A
        // instância abaixo nunca é salva — só carrega os dois campos que
        // AppointmentPolicy::createFromOwnRequest() lê.
        if (! $request->user()->can('create', [Appointment::class, $organization])) {
            $this->authorize('createFromOwnRequest', new AppointmentRequest([
                'organization_id' => $organization->id,
                'professional_id' => $professional->id,
            ]));
        }

        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail($request->string('unit_id')->value());
        $service = Service::query()->where('organization_id', $organization->id)->findOrFail($request->string('service_id')->value());
        $date = Carbon::parse($request->string('date')->value());

        $link = $this->linkResolver->resolve($organization->id, $professional->id, $service->id, $unit->id);

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

    public function confirm(Appointment $appointment, ConfirmAppointmentAction $action): RedirectResponse
    {
        $this->authorize('confirm', $appointment);

        $action->handle($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento confirmado.']);

        return back();
    }

    public function editPropose(Appointment $appointment): Response
    {
        $this->authorize('proposeAlternateTime', $appointment);

        $appointment->loadMissing(['unit', 'professional:id,display_name', 'service:id,name']);

        return Inertia::render('settings/appointments/Propose', [
            'appointment' => [
                'id' => $appointment->id,
                'unit_id' => $appointment->unit_id,
                'professional_id' => $appointment->professional_id,
                'service_id' => $appointment->service_id,
                'professional_name' => $appointment->professional->display_name,
                'service_name' => $appointment->service->name,
            ],
        ]);
    }

    public function propose(ProposeAlternateTimeRequest $request, Appointment $appointment, ProposeAlternateTimeAction $action): RedirectResponse
    {
        $duration = (int) $appointment->starts_at->diffInMinutes($appointment->ends_at);
        $startsAt = Carbon::parse($request->validated('starts_at'), $appointment->unit->timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $action->handle($appointment, $startsAt, $endsAt);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Novo horário proposto ao paciente. Aguardando confirmação.']);

        return to_route('settings.appointments.index');
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
}
