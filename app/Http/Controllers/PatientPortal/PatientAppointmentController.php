<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Actions\PatientPortal\AcceptProposedAppointmentTimeAction;
use App\Actions\PatientPortal\BookAppointmentAction;
use App\Actions\PatientPortal\CancelPatientAppointmentAction;
use App\Actions\PatientPortal\ReschedulePatientAppointmentAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\BookAppointmentRequest;
use App\Http\Requests\PatientPortal\CancelPatientAppointmentRequest;
use App\Http\Requests\PatientPortal\ReschedulePatientAppointmentRequest;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\PatientUser;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Services\Availability\StaffAppointmentSlotFinder;
use App\Support\Availability\ActiveProfessionalServiceLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Booking real pelo paciente/dependente autenticado (Etapa 3.2 do roadmap).
 * Nunca usa route-model-binding direto de Patient — mesmo padrão anti-IDOR
 * do resto do portal (ver App\Http\Controllers\PatientPortal\PatientProfileController):
 * o registro é sempre resolvido via PatientUser::patients()->findOrFail().
 * Sem Policy — disponibilidade de horário não é dado sensível de um
 * paciente específico, por isso `availableSlots()` não recebe `{patient}`.
 */
class PatientAppointmentController extends Controller
{
    public function __construct(private readonly ActiveProfessionalServiceLinkResolver $linkResolver) {}

    public function index(Request $request, string $patient): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $patientUser->patients()->findOrFail($patient);

        $appointments = $found->appointments()
            ->with(['professional:id,display_name', 'service:id,name', 'unit:id,name'])
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'professional_name' => $appointment->professional->display_name,
                'service_name' => $appointment->service->name,
                'unit_name' => $appointment->unit->name,
            ]);

        return Inertia::render('patient-portal/appointments/Index', [
            'patient' => ['id' => $found->id, 'name' => $found->preferred_name ?: $found->name],
            'appointments' => $appointments,
        ]);
    }

    public function create(Request $request, string $patient): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $patientUser->patients()->findOrFail($patient);

        $organization = Organization::query()->findOrFail($patientUser->organization_id);

        return Inertia::render('patient-portal/appointments/Create', [
            'patient' => ['id' => $found->id, 'name' => $found->preferred_name ?: $found->name],
            'units' => $organization->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'professionals' => $organization->professionals()->where('status', RecordStatus::Active)->orderBy('display_name')->get(['id', 'display_name']),
            'services' => $organization->services()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function availableSlots(Request $request, StaffAppointmentSlotFinder $finder): JsonResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $organization = Organization::query()->findOrFail($patientUser->organization_id);

        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail($request->string('unit_id')->value());
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail($request->string('professional_id')->value());
        $service = Service::query()->where('organization_id', $organization->id)->findOrFail($request->string('service_id')->value());
        $date = Carbon::parse($request->string('date')->value());

        $link = $this->linkResolver->resolve($organization->id, $professional->id, $service->id, $unit->id);

        return response()->json([
            'slots' => $finder->availableTimes($professional, $unit, $link, $date)->values(),
        ]);
    }

    public function store(BookAppointmentRequest $request, string $patient, BookAppointmentAction $action): RedirectResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $patientUser->patients()->findOrFail($patient);

        $organization = Organization::query()->findOrFail($patientUser->organization_id);
        $unit = Unit::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('unit_id'));
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('professional_id'));
        $service = Service::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('service_id'));

        $link = $this->linkResolver->resolve($organization->id, $professional->id, $service->id, $unit->id);

        // Mesma disciplina de fuso horário do staff — ver
        // App\Http\Controllers\Organization\AppointmentController::store().
        $startsAt = Carbon::parse($request->validated('starts_at'), $unit->timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($link->effectiveDurationMinutes());

        $action->handle($organization, $unit, $professional, $found, $service, $startsAt, $endsAt, $patientUser, $request->validated('notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitação de agendamento enviada. A clínica confirmará em breve.']);

        return to_route('patient-portal.appointments.index', ['patient' => $found->id]);
    }

    /**
     * Nunca usa route-model-binding direto de Appointment — mesmo padrão
     * anti-IDOR do resto do portal: o agendamento é sempre resolvido através
     * da relação do próprio paciente, nunca por ID isolado. Primeira rota do
     * portal a escopar dois níveis ({patient} e {appointment}).
     */
    private function findOwnAppointment(PatientUser $patientUser, string $patient, string $appointment): Appointment
    {
        $found = $patientUser->patients()->findOrFail($patient);

        return $found->appointments()->findOrFail($appointment);
    }

    public function cancel(CancelPatientAppointmentRequest $request, string $patient, string $appointment, CancelPatientAppointmentAction $action): RedirectResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $this->findOwnAppointment($patientUser, $patient, $appointment);

        $action->handle($found, $patientUser, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento cancelado.']);

        return to_route('patient-portal.appointments.index', ['patient' => $patient]);
    }

    public function editReschedule(Request $request, string $patient, string $appointment): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $this->findOwnAppointment($patientUser, $patient, $appointment);
        $found->loadMissing(['unit', 'professional', 'service']);

        $organization = Organization::query()->findOrFail($patientUser->organization_id);
        $link = $this->linkResolver->resolve($organization->id, $found->professional_id, $found->service_id, $found->unit_id);

        return Inertia::render('patient-portal/appointments/Reschedule', [
            'patient' => ['id' => $patient],
            'appointment' => [
                'id' => $found->id,
                'starts_at' => $found->starts_at->toIso8601String(),
                'unit_id' => $found->unit_id,
                'professional_id' => $found->professional_id,
                'service_id' => $found->service_id,
                'professional_name' => $found->professional->display_name,
                'service_name' => $found->service->name,
                'duration_minutes' => $link->effectiveDurationMinutes(),
            ],
        ]);
    }

    public function reschedule(
        ReschedulePatientAppointmentRequest $request,
        string $patient,
        string $appointment,
        ReschedulePatientAppointmentAction $action,
    ): RedirectResponse {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $this->findOwnAppointment($patientUser, $patient, $appointment);

        $duration = (int) $found->starts_at->diffInMinutes($found->ends_at);
        $startsAt = Carbon::parse($request->validated('starts_at'), $found->unit->timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $action->handle($found, $patientUser, $startsAt, $endsAt);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento reagendado com sucesso.']);

        return to_route('patient-portal.appointments.index', ['patient' => $patient]);
    }

    public function acceptProposedTime(Request $request, string $patient, string $appointment, AcceptProposedAppointmentTimeAction $action): RedirectResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $this->findOwnAppointment($patientUser, $patient, $appointment);

        $action->handle($found, $patientUser);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário confirmado.']);

        return to_route('patient-portal.appointments.index', ['patient' => $patient]);
    }
}
