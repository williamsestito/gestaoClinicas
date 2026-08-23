<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * `draft` → `finalized` (RN-007) — depois desta chamada, os campos
 * clínicos nunca são atualizados de novo por
 * `UpdateMedicalRecordDraftAction`; correções passam a exigir um
 * `App\Models\MedicalRecordAddendum` (ver `AddMedicalRecordAddendumAction`).
 */
class FinalizeMedicalRecordAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(MedicalRecord $medicalRecord): MedicalRecord
    {
        if ($medicalRecord->status !== MedicalRecordStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Este prontuário já foi finalizado.',
            ]);
        }

        $before = ['status' => $medicalRecord->status->value];

        $medicalRecord->update([
            'status' => MedicalRecordStatus::Finalized,
            'finalized_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $medicalRecord,
            before: $before,
            after: ['status' => $medicalRecord->status->value, 'finalized_at' => $medicalRecord->finalized_at?->toIso8601String()],
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return $medicalRecord;
    }
}
