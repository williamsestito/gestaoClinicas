<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Enums\RecordStatus;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivatePatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Patient $patient): Patient
    {
        if ($patient->organization->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'patient' => 'Não é possível ativar um paciente de uma clínica que não está ativa.',
            ]);
        }

        $previousStatus = $patient->status;

        $patient->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $patient,
            before: ['status' => $previousStatus->value],
            after: ['status' => $patient->status->value],
            organization: $patient->organization,
        );

        return $patient;
    }
}
