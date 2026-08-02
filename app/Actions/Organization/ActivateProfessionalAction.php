<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional): Professional
    {
        if ($professional->organization->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'professional' => 'Não é possível ativar um profissional de uma clínica que não está ativa.',
            ]);
        }

        $previousStatus = $professional->status;

        $professional->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $professional,
            before: ['status' => $previousStatus->value],
            after: ['status' => $professional->status->value],
            organization: $professional->organization,
        );

        return $professional;
    }
}
