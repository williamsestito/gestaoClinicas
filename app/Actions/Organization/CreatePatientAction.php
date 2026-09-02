<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\AddressData;
use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;
use App\Support\Patients\MinorGuardianGuard;
use App\Support\Patients\OrphanAppointmentRequestLinker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria o paciente com endereço opcional, contatos de emergência (RN-003,
 * ao menos um) e responsáveis (RN-004, obrigatório se menor de 18) — tudo
 * em uma transação. As invariantes já são checadas em
 * App\Http\Requests\Organization\CreatePatientRequest, mas são revalidadas
 * aqui porque a Action é o limite final de segurança (pode ser chamada de
 * outros pontos, ex.: Filament, sem passar pelo Form Request).
 */
class CreatePatientAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OrphanAppointmentRequestLinker $orphanAppointmentRequestLinker,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $emergencyContacts
     * @param  list<array<string, mixed>>  $responsibles
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?AddressData $address,
        array $emergencyContacts,
        array $responsibles,
    ): Patient {
        if ($emergencyContacts === []) {
            throw ValidationException::withMessages([
                'emergency_contacts' => 'Informe ao menos um contato de emergência.',
            ]);
        }

        if (MinorGuardianGuard::isMinor((string) $attributes['birth_date']) && ! MinorGuardianGuard::hasLegalGuardianInPayload($responsibles)) {
            throw ValidationException::withMessages([
                'responsibles' => 'Paciente menor de 18 anos precisa de ao menos um responsável legal informado.',
            ]);
        }

        return DB::transaction(function () use ($organization, $attributes, $address, $emergencyContacts, $responsibles) {
            try {
                $patient = $organization->patients()->create([
                    ...$attributes,
                    'status' => RecordStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe um paciente com este documento nesta clínica.',
                ]);
            }

            if ($address !== null) {
                $patient->address()->create([
                    ...$address->toArray(),
                    'organization_id' => $organization->id,
                ]);
            }

            foreach ($emergencyContacts as $contact) {
                $patient->emergencyContacts()->create([
                    ...$contact,
                    'organization_id' => $organization->id,
                ]);
            }

            foreach ($responsibles as $responsible) {
                $patient->responsibles()->create([
                    ...$responsible,
                    'organization_id' => $organization->id,
                ]);
            }

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $patient,
                after: $patient->only(['name', 'birth_date', 'status']),
                organization: $organization,
            );

            $this->orphanAppointmentRequestLinker->link($patient);

            return $patient;
        });
    }
}
