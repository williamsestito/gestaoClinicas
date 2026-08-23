<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Libera um prontuário já finalizado para o portal do paciente (RN-014:
 * "paciente só visualiza registros finalizados e liberados" — dois flags
 * deliberadamente separados de `finalized_at`).
 */
class ReleaseMedicalRecordToPatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(MedicalRecord $medicalRecord): MedicalRecord
    {
        if ($medicalRecord->status !== MedicalRecordStatus::Finalized) {
            throw ValidationException::withMessages([
                'status' => 'Só é possível liberar ao paciente um prontuário já finalizado.',
            ]);
        }

        $medicalRecord->update(['released_to_patient_at' => now()]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $medicalRecord,
            before: ['released_to_patient_at' => null],
            after: ['released_to_patient_at' => $medicalRecord->released_to_patient_at?->toIso8601String()],
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return $medicalRecord;
    }
}
