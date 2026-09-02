<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Enums\RecordStatus;
use App\Models\Specialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Specialty $specialty): Specialty
    {
        if ($specialty->organization->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'specialty' => 'Não é possível ativar uma especialidade de uma clínica que não está ativa.',
            ]);
        }

        $previousStatus = $specialty->status;

        $specialty->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $specialty,
            before: ['status' => $previousStatus->value],
            after: ['status' => $specialty->status->value],
            organization: $specialty->organization,
        );

        return $specialty;
    }
}
