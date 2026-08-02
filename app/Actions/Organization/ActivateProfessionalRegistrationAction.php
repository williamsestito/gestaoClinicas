<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;

class ActivateProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalRegistration $registration): ProfessionalRegistration
    {
        $previousStatus = $registration->status;

        $registration->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $registration,
            before: ['status' => $previousStatus->value],
            after: ['status' => $registration->status->value],
            organization: $registration->organization,
        );

        return $registration;
    }
}
