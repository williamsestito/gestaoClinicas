<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Specialty;
use App\Support\Auditing\AuditLogger;

/**
 * Inativar uma especialidade nunca destrói os vínculos existentes com
 * profissionais ou serviços (sem cascata silenciosa) — ela apenas deixa de
 * poder ser usada em novos vínculos a partir das telas correspondentes.
 */
class DeactivateSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Specialty $specialty): Specialty
    {
        $previousStatus = $specialty->status;

        $specialty->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $specialty,
            before: ['status' => $previousStatus->value],
            after: ['status' => $specialty->status->value],
            organization: $specialty->organization,
        );

        return $specialty;
    }
}
