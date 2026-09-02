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
use App\Models\Professional;
use App\Models\Unit;
use App\Notifications\NewAppointmentRequestNotification;
use App\Services\Availability\BookedRangeResolver;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\ActiveProfessionalServiceLinkResolver;
use App\Support\Availability\AppointmentOverlapGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ActiveProfessionalServiceLinkResolver $linkResolver,
        private readonly BookedRangeResolver $bookedRangeResolver,
    ) {}

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

        $preferredStartsAt = $this->resolvePreferredStartsAt($data, $unit);

        // Checagem + criação na mesma transação: o advisory lock adquirido
        // dentro de guardAgainstSlotConflict() (via AppointmentOverlapGuard,
        // reentrante por profissional) só serializa duas requisições
        // concorrentes para o mesmo profissional/horário enquanto ambas
        // continuarem na mesma transação até o INSERT — fora de uma
        // transação, o lock seria liberado antes do create(), reabrindo a
        // janela de corrida (achado explícito: "podemos ter inúmeros
        // pacientes realizando pré-agendamentos simultaneamente").
        $appointmentRequest = DB::transaction(function () use ($data, $organization, $unit, $patientId, $preferredStartsAt) {
            $this->guardAgainstSlotConflict($data, $organization, $unit, $preferredStartsAt);

            return AppointmentRequest::query()->create([
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
                'preferred_starts_at' => $preferredStartsAt,
                'notes' => $data['notes'] ?? null,
                'utm_data' => array_filter($data['utm'] ?? []) ?: null,
                'status' => AppointmentRequestStatus::Pending,
                'terms_accepted_at' => now(),
            ]);
        });

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
     * Só verifica conflito quando o lead veio de um horário específico
     * escolhido na busca de disponibilidade (profissional + serviço
     * operacional + horário exato todos presentes) — nunca para o
     * formulário manual, que só carrega preferência aproximada (dia/
     * período), sem profissional/serviço/horário resolvidos o suficiente
     * para checar sobreposição de agenda de verdade.
     *
     * Mesmos guards da criação real de agendamento
     * (App\Actions\Organization\CreateAppointmentAction/
     * App\Actions\PatientPortal\BookAppointmentAction) — bloqueia no
     * momento em que o cliente envia a solicitação, em vez de só na
     * conversão pelo staff, quando a recepção já teria perdido tempo
     * tentando confirmar um horário impossível (achado em uso real).
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstSlotConflict(array $data, ?Organization $organization, ?Unit $unit, ?Carbon $preferredStartsAt): void
    {
        if ($preferredStartsAt === null || $organization === null || $unit === null) {
            return;
        }

        $professionalId = $data['professional_id'] ?? null;
        $serviceId = $data['preferred_service_id'] ?? null;

        if ($professionalId === null || $serviceId === null) {
            return;
        }

        $professional = Professional::query()->where('organization_id', $organization->id)->find((string) $professionalId);

        if ($professional === null) {
            return;
        }

        $link = $this->linkResolver->resolve($organization->id, $professional->id, $serviceId, $unit->id);
        $endsAt = $preferredStartsAt->copy()->addMinutes($link->effectiveDurationMinutes());

        AppointmentOverlapGuard::assertWithinAvailability($professional, $unit, $preferredStartsAt, $endsAt);
        AppointmentOverlapGuard::assertNoConflict($professional, $preferredStartsAt, $endsAt, allowOverlap: $organization->allow_appointment_overlap);

        // AppointmentOverlapGuard::assertNoConflict() só enxerga Appointment
        // de verdade — dois pré-agendamentos PENDENTES (nunca convertidos)
        // para o mesmo profissional no mesmo horário exato não caem nela.
        // Reaproveita o mesmo advisory lock adquirido pela chamada acima
        // (dentro da mesma transação de handle()) para também checar
        // contra outros pré-agendamentos em aberto — sem isso, dois
        // pacientes enviando ao mesmo tempo conseguiam os dois "pedir" o
        // mesmo horário, e só o segundo a ser CONVERTIDO pelo staff
        // descobriria o conflito.
        if (! $organization->allow_appointment_overlap) {
            $localStart = $preferredStartsAt->copy()->setTimezone($unit->timezone)->format('H:i');
            $localEnd = $endsAt->copy()->setTimezone($unit->timezone)->format('H:i');
            $bookedRanges = $this->bookedRangeResolver->forProfessionalOnDate($professional, $unit, $preferredStartsAt);

            if (BookedRangeResolver::overlapsAny($localStart, $localEnd, $bookedRanges)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Este horário acabou de ser solicitado por outro paciente e aguarda confirmação da clínica. Escolha outro horário.',
                ]);
            }
        }
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
            $ownPatient = Patient::query()
                ->whereIn('id', PatientUserLink::query()
                    ->where('patient_user_id', $patientUser->id)
                    ->where('organization_id', $organization->id)
                    ->where('role', PatientUserLinkRole::Self)
                    ->select('patient_id'))
                ->first(['id', 'document']);

            $submittedDocument = $data['document'] ?? null;

            // O CPF digitado descreve claramente outra pessoa (bate com
            // nenhum, não com o da própria conta logada) — nunca força o
            // vínculo à conta autenticada nesse caso, cai no mesmo
            // matching do lead anônimo. Achado em uso real: um paciente
            // logado enviando o formulário com dados de outra pessoa
            // (nome/CPF/telefone diferentes) ficava com o lead preso à
            // própria conta, nunca ao paciente certo.
            if ($ownPatient !== null && $submittedDocument !== null && $ownPatient->document !== $submittedDocument) {
                return $this->matchExistingPatient($data, $organization)?->id;
            }

            return $ownPatient?->id;
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
