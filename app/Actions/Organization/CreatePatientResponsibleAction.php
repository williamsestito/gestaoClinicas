<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Patient;
use App\Models\PatientResponsible;
use App\Support\Auditing\AuditLogger;

class CreatePatientResponsibleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Patient $patient, array $attributes): PatientResponsible
    {
        $responsible = $patient->responsibles()->create([
            ...$attributes,
            'organization_id' => $patient->organization_id,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $responsible,
            after: $responsible->only(['name', 'is_legal_guardian', 'is_financial_responsible', 'is_authorized_representative']),
            organization: $patient->organization,
        );

        return $responsible;
    }
}
