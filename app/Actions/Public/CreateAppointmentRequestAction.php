<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PatientUserLinkRole;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use App\Models\Unit;
use App\Notifications\NewAppointmentRequestNotification;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cria uma solicitação de agendamento (lead) vinda da landing pública.
 * Reaproveita uma solicitação recente idêntica em vez de duplicá-la (mesmo
 * telefone/serviço/data/período numa janela curta — duplo clique, F5 no
 * "obrigado"), audita a criação e avisa a clínica por e-mail sem deixar uma
 * falha de notificação afetar a solicitação já persistida.
 */
class CreateAppointmentRequestAction
{
    private const DUPLICATE_WINDOW_MINUTES = 10;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Organization $organization, ?Unit $unit, ?PatientUser $patientUser = null): AppointmentRequest
    {
        $existing = $this->findRecentDuplicate($data, $organization);

        if ($existing !== null) {
            return $existing;
        }

        $patientId = $this->resolvePatientId($data, $organization, $patientUser);

        $this->guardAgainstPendingDuplicateProfessional($patientId, $data['professional_id'] ?? null, $organization);

        $appointmentRequest = AppointmentRequest::query()->create([
            'organization_id' => $organization?->id,
            'unit_id' => $unit?->id,
            'service_id' => $data['service_id'] ?? null,
            'preferred_service_id' => $data['preferred_service_id'] ?? null,
            'professional_id' => $data['professional_id'] ?? null,
            'patient_id' => $patientId,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'document' => $data['document'] ?? null,
            'preferred_period' => $data['preferred_period'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_starts_at' => $this->resolvePreferredStartsAt($data, $unit),
            'notes' => $data['notes'] ?? null,
            'utm_data' => array_filter($data['utm'] ?? []) ?: null,
            'status' => AppointmentRequestStatus::Pending,
            'terms_accepted_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $appointmentRequest,
            after: [
                'name' => $appointmentRequest->name,
                'phone' => $appointmentRequest->phone,
                'service_id' => $appointmentRequest->service_id,
                'professional_id' => $appointmentRequest->professional_id,
                'patient_id' => $appointmentRequest->patient_id,
                'status' => $appointmentRequest->status->value,
            ],
            organization: $organization,
            unit: $unit,
        );

        $this->notifyOwners($appointmentRequest, $organization);

        return $appointmentRequest;
    }

    /**
     * O horário exato escolhido na busca de disponibilidade
     * (`preferred_starts_at`) chega como hora civil local (mesmo valor
     * devolvido por `PublicAvailabilityFinder::availableTimes()`), nunca
     * já em UTC — mesma disciplina de fuso de
     * `AppointmentController::store()`. Sem unidade resolvida (lead
     * anônimo antes de qualquer organização existir), não há fuso para
     * converter, então o horário exato é descartado — só os campos
     * aproximados (`preferred_date`/`preferred_period`) sobrevivem.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePreferredStartsAt(array $data, ?Unit $unit): ?Carbon
    {
        if (empty($data['preferred_starts_at']) || $unit === null) {
            return null;
        }

        return Carbon::parse($data['preferred_starts_at'], $unit->timezone)->utc();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findRecentDuplicate(array $data, ?Organization $organization): ?AppointmentRequest
    {
        $query = AppointmentRequest::query()
            ->when($organization, fn (Builder $q) => $q->where('organization_id', $organization->id))
            ->where('phone', $data['phone'])
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES));

        $query = $this->matchNullable($query, 'service_id', $data['service_id'] ?? null);
        $query = $this->matchNullable($query, 'professional_id', $data['professional_id'] ?? null);
        $query = $this->matchNullable($query, 'preferred_date', $data['preferred_date'] ?? null);
        $query = $this->matchNullable($query, 'preferred_period', $data['preferred_period'] ?? null);

        return $query->latest()->first();
    }

    /**
     * @param  Builder<AppointmentRequest>  $query
     * @return Builder<AppointmentRequest>
     */
    private function matchNullable(Builder $query, string $column, mixed $value): Builder
    {
        return $value === null ? $query->whereNull($column) : $query->where($column, $value);
    }

    /**
     * Vincula a solicitação a um paciente já cadastrado, quando existir —
     * evita que a clínica precise cruzar leads e cadastros manualmente.
     * Quando quem envia já está logado no portal, o vínculo é direto (o
     * próprio paciente da conta, papel "self"); anônimo, tenta localizar por
     * CPF, depois telefone, depois e-mail, na ordem de confiabilidade —
     * nunca cria nem altera um Patient a partir de um lead público.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePatientId(array $data, ?Organization $organization, ?PatientUser $patientUser): ?string
    {
        if ($organization === null) {
            return null;
        }

        if ($patientUser !== null) {
            return PatientUserLink::query()
                ->where('patient_user_id', $patientUser->id)
                ->where('organization_id', $organization->id)
                ->where('role', PatientUserLinkRole::Self)
                ->value('patient_id');
        }

        return $this->matchExistingPatient($data, $organization)?->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function matchExistingPatient(array $data, Organization $organization): ?Patient
    {
        $document = $data['document'] ?? null;

        if ($document !== null) {
            $byDocument = Patient::query()
                ->where('organization_id', $organization->id)
                ->where('document', $document)
                ->first();

            if ($byDocument !== null) {
                return $byDocument;
            }
        }

        $byPhone = Patient::query()
            ->where('organization_id', $organization->id)
            ->where(fn (Builder $query) => $query
                ->where('phone', $data['phone'])
                ->orWhere('whatsapp', $data['phone']))
            ->first();

        if ($byPhone !== null) {
            return $byPhone;
        }

        if (empty($data['email'])) {
            return null;
        }

        return Patient::query()
            ->where('organization_id', $organization->id)
            ->where('email', $data['email'])
            ->first();
    }

    /**
     * Só bloqueia quando o paciente já é reconhecido (por CPF/telefone/
     * e-mail ou conta logada) E já tem uma solicitação Pending com o MESMO
     * profissional — evita a pessoa (ou um clique duplo/F5 fora da janela
     * curta de findRecentDuplicate()) empilhar vários pré-agendamentos
     * idênticos aguardando contato, sem impedir uma nova solicitação para
     * um profissional diferente, ou depois que a anterior mudar de status
     * (contatada/agendada/cancelada).
     */
    private function guardAgainstPendingDuplicateProfessional(?string $patientId, ?string $professionalId, ?Organization $organization): void
    {
        if ($patientId === null || $professionalId === null) {
            return;
        }

        $hasPending = AppointmentRequest::query()
            ->where('organization_id', $organization?->id)
            ->where('patient_id', $patientId)
            ->where('professional_id', $professionalId)
            ->where('status', AppointmentRequestStatus::Pending)
            ->exists();

        if (! $hasPending) {
            return;
        }

        throw ValidationException::withMessages([
            'professional_id' => 'Você já tem uma solicitação aguardando contato com este profissional. Aguarde o retorno da clínica ou cancele a solicitação atual antes de enviar uma nova.',
        ]);
    }

    private function notifyOwners(AppointmentRequest $appointmentRequest, ?Organization $organization): void
    {
        if ($organization === null) {
            return;
        }

        try {
            $organization->memberships()
                ->where('is_owner', true)
                ->where('status', OrganizationMembershipStatus::Active)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->each(fn ($owner) => $owner->notify(new NewAppointmentRequestNotification($appointmentRequest)));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
