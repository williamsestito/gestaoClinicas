<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;

class DeactivatePatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Patient $patient): Patient
    {
        $previousStatus = $patient->status;

        $patient->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $patient,
            before: ['status' => $previousStatus->value],
            after: ['status' => $patient->status->value],
            organization: $patient->organization,
        );

        return $patient;
    }
}
