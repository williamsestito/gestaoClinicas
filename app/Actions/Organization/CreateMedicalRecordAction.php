<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Support\Auditing\AuditLogger;

/**
 * Abre o prontuário (rascunho) de um atendimento — idempotente: se já
 * existe um registro para o `Appointment` (FK única), devolve o existente
 * em vez de tentar criar de novo, já que "abrir o prontuário" pode ser
 * clicado mais de uma vez pelo profissional sem problema.
 */
class CreateMedicalRecordAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment): MedicalRecord
    {
        $existing = MedicalRecord::query()->where('appointment_id', $appointment->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $medicalRecord = MedicalRecord::query()->create([
            'organization_id' => $appointment->organization_id,
            'unit_id' => $appointment->unit_id,
            'patient_id' => $appointment->patient_id,
            'professional_id' => $appointment->professional_id,
            'appointment_id' => $appointment->id,
            // Explícito em vez de confiar no default da coluna no banco —
            // sem isso, o atributo em memória fica null até um refresh().
            'status' => MedicalRecordStatus::Draft,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $medicalRecord,
            after: ['status' => $medicalRecord->status->value],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $medicalRecord;
    }
}
