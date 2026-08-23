<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalDashboardReminder;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** @var array<int, string> */
    private const PERIODS = ['day', 'week', 'month'];

    public function index(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $professional = $this->resolveOwnDashboardProfessional($tenant);

        if ($professional !== null) {
            return Inertia::render('Dashboard', [
                'professionalDashboard' => $this->professionalDashboardData($request, $organization, $professional),
            ] + $this->adminDashboardData($organization));
        }

        return Inertia::render('Dashboard', [
            'professionalDashboard' => null,
        ] + $this->adminDashboardData($organization));
    }

    /**
     * Só entra no dashboard do profissional quando o papel ativo é
     * exatamente "Profissional" (nunca proprietário/administrador, mesmo
     * que também tenham um cadastro de profissional vinculado) — quem
     * administra a clínica continua vendo a visão geral existente.
     */
    private function resolveOwnDashboardProfessional(TenantContext $tenant): ?Professional
    {
        $organization = $tenant->organization();
        $membership = $tenant->membership();

        if ($organization === null || $membership === null || $membership->is_owner) {
            return null;
        }

        if ($membership->role?->slug !== SystemRole::Professional->value) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->professionals()
            ->where('organization_id', $organization->id)
            ->where('status', RecordStatus::Active)
            ->first();
    }

    /** @return array<string, mixed> */
    private function professionalDashboardData(Request $request, ?Organization $organization, Professional $professional): array
    {
        if ($organization === null) {
            return [];
        }

        $period = in_array($request->string('period')->value(), self::PERIODS, true)
            ? $request->string('period')->value()
            : 'day';
        $referenceDate = $request->filled('date')
            ? Carbon::parse($request->string('date')->value())
            : Carbon::today();

        [$start, $end] = $this->periodRange($period, $referenceDate);

        $baseQuery = fn () => Appointment::query()
            ->where('organization_id', $organization->id)
            ->where('professional_id', $professional->id)
            ->whereBetween('starts_at', [$start, $end]);

        $counters = [
            'open' => $baseQuery()->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::AwaitingConfirmation])->count(),
            'scheduled' => $baseQuery()->where('status', AppointmentStatus::Confirmed)->count(),
            'completed' => $baseQuery()->where('status', AppointmentStatus::Completed)->count(),
        ];

        $agendaLimit = 200;
        $agenda = $baseQuery()
            ->with(['patient:id,name,preferred_name', 'service:id,name', 'unit:id,name', 'medicalRecord:id,appointment_id'])
            ->orderBy('starts_at')
            ->limit($agendaLimit)
            ->get();

        // Etapa 4 — lembrete aditivo, nunca bloqueia a conclusão do
        // atendimento (decisão explícita do usuário): atendimentos
        // concluídos no período sem nenhum prontuário aberto ainda.
        $completedWithoutMedicalRecordCount = $baseQuery()
            ->where('status', AppointmentStatus::Completed)
            ->whereDoesntHave('medicalRecord')
            ->count();

        $pendingRequests = AppointmentRequest::query()
            ->with('service:id,name')
            ->where('organization_id', $organization->id)
            ->where('professional_id', $professional->id)
            ->where('status', AppointmentRequestStatus::Pending)
            ->latest()
            ->get();

        $reminders = ProfessionalDashboardReminder::query()
            ->where('organization_id', $organization->id)
            ->where('professional_id', $professional->id)
            ->latest()
            ->get();

        return [
            'period' => $period,
            'referenceDate' => $referenceDate->toDateString(),
            'rangeLabel' => $this->rangeLabel($period, $start, $end),
            'counters' => $counters,
            'agenda' => $agenda->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'patient_name' => $appointment->patient->preferred_name ?: $appointment->patient->name,
                'service_name' => $appointment->service->name,
                'unit_name' => $appointment->unit->name,
                'medical_record_id' => $appointment->medicalRecord?->id,
            ])->values(),
            'agendaTruncated' => $agenda->count() >= $agendaLimit,
            'completedWithoutMedicalRecordCount' => $completedWithoutMedicalRecordCount,
            'pendingAppointmentRequestsCount' => $pendingRequests->count(),
            'pendingAppointmentRequests' => $pendingRequests->take(5)->map(fn (AppointmentRequest $appointmentRequest) => [
                'id' => $appointmentRequest->id,
                'name' => $appointmentRequest->name,
                'phone' => $appointmentRequest->phone,
                'service_name' => $appointmentRequest->service?->name,
                'created_at' => $appointmentRequest->created_at?->toIso8601String(),
            ])->values(),
            'reminders' => $reminders->map(fn (ProfessionalDashboardReminder $reminder) => [
                'id' => $reminder->id,
                'body' => $reminder->body,
                'color' => $reminder->color->value,
                'alarm_at' => $reminder->alarm_at?->toIso8601String(),
                'created_at' => $reminder->created_at->toIso8601String(),
            ])->values(),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(string $period, Carbon $referenceDate): array
    {
        return match ($period) {
            'week' => [$referenceDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(), $referenceDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay()],
            'month' => [$referenceDate->copy()->startOfMonth(), $referenceDate->copy()->endOfMonth()->endOfDay()],
            default => [$referenceDate->copy()->startOfDay(), $referenceDate->copy()->endOfDay()],
        };
    }

    private function rangeLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'week' => sprintf('%s a %s', $start->translatedFormat('d/m'), $end->translatedFormat('d/m')),
            'month' => $start->translatedFormat('F \d\e Y'),
            default => $start->translatedFormat('d/m/Y'),
        };
    }

    /** @return array<string, mixed> */
    private function adminDashboardData(?Organization $organization): array
    {
        $primaryLegalEntity = $organization?->primaryLegalEntity()->first();
        $siteSetting = SiteSetting::query()->first();

        $usersCount = 0;
        $activeUsersCount = 0;
        $inactiveUsersCount = 0;

        if ($organization) {
            $usersCount = $organization->memberships()->count();
            $activeUsersCount = $organization->memberships()
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->count();
            $inactiveUsersCount = $usersCount - $activeUsersCount;
        }

        $recentActivity = $organization
            ? AuditLog::query()
                ->where('organization_id', $organization->id)
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'actor_user_id', 'action', 'auditable_type', 'created_at'])
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'actor' => $log->actor ? $log->actor->name : 'Sistema',
                    'action' => $log->action->label(),
                    'entity' => class_basename((string) $log->auditable_type),
                    'created_at' => $log->created_at->toIso8601String(),
                ])
            : collect();

        $domainConfigured = $siteSetting !== null && filled($siteSetting->official_domain);
        $seoConfigured = $siteSetting !== null && filled($siteSetting->meta_title) && filled($siteSetting->meta_description);

        return [
            'organizationName' => $organization?->name,
            'unitsCount' => $organization?->units()->count() ?? 0,
            'usersCount' => $usersCount,
            'activeUsersCount' => $activeUsersCount,
            'inactiveUsersCount' => $inactiveUsersCount,
            'legalEntitiesCount' => $organization?->legalEntities()->count() ?? 0,
            'primaryLegalEntity' => $primaryLegalEntity ? [
                'legal_name' => $primaryLegalEntity->legal_name,
                'trade_name' => $primaryLegalEntity->trade_name,
            ] : null,
            'domainConfigured' => $domainConfigured,
            'seoConfigured' => $seoConfigured,
            'recentActivity' => $recentActivity,
            'pendingSetupItems' => $this->pendingSetupItems($organization, $primaryLegalEntity, $siteSetting),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function pendingSetupItems(
        ?Organization $organization,
        ?LegalEntity $primaryLegalEntity,
        ?SiteSetting $siteSetting,
    ): array {
        $items = [];

        if (! $primaryLegalEntity) {
            $items[] = 'Cadastre uma entidade legal principal.';
        }

        if ($organization && ! $organization->headquarters()->exists()) {
            $items[] = 'Defina uma unidade matriz.';
        }

        if (! $siteSetting?->official_domain) {
            $items[] = 'Configure o domínio oficial do site.';
        }

        if (! $siteSetting || blank($siteSetting->meta_title) || blank($siteSetting->meta_description)) {
            $items[] = 'Complete os metadados de SEO da página pública.';
        }

        return $items;
    }
}
