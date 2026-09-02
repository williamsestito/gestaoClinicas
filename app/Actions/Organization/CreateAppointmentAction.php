<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Enums\WaitlistEntryStatus;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\SharedResource;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\AppointmentOverlapGuard;
use App\Support\Availability\ResourceOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sempre revalida disponibilidade e conflito no servidor — nunca confia no
 * horário sugerido pelo frontend (App\Services\Availability\StaffAppointmentSlotFinder
 * é só uma sugestão, não autoritativa). Criado por staff, entra sempre em
 * `Confirmed` (ver docs/roadmap.md, Etapa 3.1).
 *
 * `$sourceRequest` (Etapa 3.2), quando presente, marca o lead de origem como
 * convertido na mesma transação — ver docs/modules/appointments.md.
 *
 * `$resourceIds` (Etapa 3.3) vincula 0-N recursos (salas/equipamentos) —
 * cada um revalidado por App\Support\Availability\ResourceOverlapGuard,
 * nunca dispensado pelo toggle de "encaixe" (esse relaxa só o conflito de
 * profissional). Quando `$organization->allow_appointment_overlap` está
 * ativo e há conflito de profissional, a criação segue em frente (em vez
 * de lançar `ValidationException`), mas audita `AuditAction::ConflictDetected`.
 */
class CreateAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param list<string> $resourceIds */
    public function handle(
        Organization $organization,
        Unit $unit,
        Professional $professional,
        Patient $patient,
        Service $service,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $notes = null,
        ?AppointmentRequest $sourceRequest = null,
        array $resourceIds = [],
        ?SessionPackage $sessionPackage = null,
        ?string $recurrenceGroupId = null,
        ?WaitlistEntry $sourceWaitlistEntry = null,
    ): Appointment {
        return DB::transaction(function () use ($organization, $unit, $professional, $patient, $service, $startsAt, $endsAt, $notes, $sourceRequest, $resourceIds, $sessionPackage, $recurrenceGroupId, $sourceWaitlistEntry) {
            // Trava a linha do lead e revalida antes de criar qualquer coisa —
            // agora que profissional, admin e atendimento podem confirmar o
            // mesmo pré-agendamento simultaneamente, sem isso duas requisições
            // concorrentes leriam `appointment_id === null` ao mesmo tempo (a
            // instância já carregada fora da transação) e ambas converteriam o
            // mesmo lead, criando dois Appointments reais.
            if ($sourceRequest !== null) {
                $sourceRequest = AppointmentRequest::query()->whereKey($sourceRequest->id)->lockForUpdate()->firstOrFail();
                $this->assertSourceRequestConvertible($sourceRequest, $organization);
            }

            AppointmentOverlapGuard::assertWithinAvailability($professional, $unit, $startsAt, $endsAt);
            $hadConflict = AppointmentOverlapGuard::assertNoConflict($professional, $startsAt, $endsAt, allowOverlap: $organization->allow_appointment_overlap);

            $resources = $this->resolveResources($organization, $resourceIds);
            foreach ($resources as $resource) {
                ResourceOverlapGuard::assertNoConflict($resource, $startsAt, $endsAt);
            }

            if ($sessionPackage !== null) {
                $this->assertSessionPackageUsable($sessionPackage, $organization, $patient);
            }

            $appointment = Appointment::query()->create([
                'organization_id' => $organization->id,
                'unit_id' => $unit->id,
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'session_package_id' => $sessionPackage?->id,
                'recurrence_group_id' => $recurrenceGroupId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Confirmed,
                'notes' => $notes,
            ]);

            if ($resources->isNotEmpty()) {
                $appointment->resources()->sync(
                    $resources->mapWithKeys(fn (SharedResource $resource) => [$resource->id => ['organization_id' => $organization->id]]),
                );
            }

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $appointment,
                after: [
                    'professional_id' => $professional->id,
                    'patient_id' => $patient->id,
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $endsAt->toIso8601String(),
                    'status' => $appointment->status->value,
                    'resource_ids' => $resources->pluck('id')->all(),
                ],
                organization: $organization,
                unit: $unit,
            );

            if ($hadConflict) {
                $this->auditLogger->log(
                    AuditAction::ConflictDetected,
                    auditable: $appointment,
                    after: ['professional_id' => $professional->id, 'starts_at' => $startsAt->toIso8601String()],
                    organization: $organization,
                    unit: $unit,
                );
            }

            if ($sourceRequest !== null) {
                $this->convertSourceRequest($sourceRequest, $appointment, $organization, $unit);
            }

            if ($sourceWaitlistEntry !== null) {
                $this->convertSourceWaitlistEntry($sourceWaitlistEntry, $appointment, $organization, $unit);
            }

            return $appointment;
        });
    }

    /**
     * @param  list<string>  $resourceIds
     * @return Collection<int, SharedResource>
     */
    private function resolveResources(Organization $organization, array $resourceIds): Collection
    {
        if ($resourceIds === []) {
            return collect();
        }

        $resources = SharedResource::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $resourceIds)
            ->get();

        if ($resources->count() !== count($resourceIds)) {
            throw ValidationException::withMessages([
                'resource_ids' => 'Um ou mais recursos selecionados não pertencem a esta organização.',
            ]);
        }

        return $resources;
    }

    private function assertSessionPackageUsable(SessionPackage $sessionPackage, Organization $organization, Patient $patient): void
    {
        if ($sessionPackage->organization_id !== $organization->id || $sessionPackage->patient_id !== $patient->id) {
            throw ValidationException::withMessages([
                'session_package_id' => 'Este pacote de sessões não pertence a este paciente.',
            ]);
        }

        if ($sessionPackage->status !== RecordStatus::Active) {
            throw ValidationException::withMessages([
                'session_package_id' => 'Este pacote de sessões está encerrado.',
            ]);
        }

        if ($sessionPackage->isExpired()) {
            throw ValidationException::withMessages([
                'session_package_id' => 'Este pacote de sessões está expirado.',
            ]);
        }

        if ($sessionPackage->remainingSessions() <= 0) {
            throw ValidationException::withMessages([
                'session_package_id' => 'Este pacote de sessões não tem mais sessões restantes.',
            ]);
        }
    }

    private function assertSourceRequestConvertible(AppointmentRequest $sourceRequest, Organization $organization): void
    {
        if ($sourceRequest->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'appointment_request_id' => 'Este lead não pertence à organização ativa.',
            ]);
        }

        if ($sourceRequest->appointment_id !== null) {
            throw ValidationException::withMessages([
                'appointment_request_id' => 'Este pré-agendamento já foi confirmado por outro usuário. Atualize a página.',
            ]);
        }

        if ($sourceRequest->status === AppointmentRequestStatus::Cancelled) {
            throw ValidationException::withMessages([
                'appointment_request_id' => 'Não é possível converter um lead cancelado.',
            ]);
        }
    }

    /**
     * Chamado só depois de assertSourceRequestConvertible() ter validado o
     * mesmo registro travado (`lockForUpdate()`) dentro desta transação —
     * sem revalidar de novo aqui para não mascarar o cenário de corrida com
     * uma segunda leitura fora do lock.
     */
    private function convertSourceRequest(AppointmentRequest $sourceRequest, Appointment $appointment, Organization $organization, Unit $unit): void
    {
        $before = ['status' => $sourceRequest->status->value, 'appointment_id' => null];

        $sourceRequest->update([
            'status' => AppointmentRequestStatus::Scheduled,
            'appointment_id' => $appointment->id,
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $sourceRequest,
            before: $before,
            after: ['status' => $sourceRequest->status->value, 'appointment_id' => $appointment->id],
            organization: $organization,
            unit: $unit,
        );
    }

    /**
     * Mesmo formato de convertSourceRequest() — não unificado numa
     * interface comum de propósito, para não introduzir abstração
     * especulativa por dois usos concretos apenas.
     */
    private function convertSourceWaitlistEntry(WaitlistEntry $sourceWaitlistEntry, Appointment $appointment, Organization $organization, Unit $unit): void
    {
        if ($sourceWaitlistEntry->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => 'Esta entrada da lista de espera não pertence à organização ativa.',
            ]);
        }

        if ($sourceWaitlistEntry->appointment_id !== null) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => 'Esta entrada já foi convertida em um agendamento.',
            ]);
        }

        if ($sourceWaitlistEntry->status === WaitlistEntryStatus::Cancelled) {
            throw ValidationException::withMessages([
                'waitlist_entry_id' => 'Não é possível converter uma entrada cancelada.',
            ]);
        }

        $before = ['status' => $sourceWaitlistEntry->status->value, 'appointment_id' => null];

        $sourceWaitlistEntry->update([
            'status' => WaitlistEntryStatus::Converted,
            'appointment_id' => $appointment->id,
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $sourceWaitlistEntry,
            before: $before,
            after: ['status' => $sourceWaitlistEntry->status->value, 'appointment_id' => $appointment->id],
            organization: $organization,
            unit: $unit,
        );
    }
}
