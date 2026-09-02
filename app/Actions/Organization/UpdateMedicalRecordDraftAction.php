<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Só atualiza enquanto `draft` (RN-007) — a Policy já bloqueia isso antes
 * de chegar aqui, mas a Action revalida porque é o limite final de
 * segurança (pode ser chamada de outros pontos sem passar pela Policy).
 */
class UpdateMedicalRecordDraftAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(MedicalRecord $medicalRecord, array $attributes): MedicalRecord
    {
        if ($medicalRecord->status !== MedicalRecordStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Este prontuário já foi finalizado e não pode mais ser editado diretamente.',
            ]);
        }

        $before = $medicalRecord->only(array_keys($attributes));
        $medicalRecord->update($attributes);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $medicalRecord,
            before: $before,
            after: $medicalRecord->only(array_keys($attributes)),
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return $medicalRecord;
    }
}
