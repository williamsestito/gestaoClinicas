<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalWorkingHour;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\WorkingHourOpeningHoursGuard;
use App\Support\Availability\WorkingHourOverlapGuard;

/**
 * Restaura sempre inativo — reativação é uma decisão explícita separada
 * (App\Actions\Organization\ActivateProfessionalWorkingHourAction), que já
 * revalida sobreposição e funcionamento da unidade.
 */
class RestoreProfessionalWorkingHourAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalWorkingHour $workingHour): ProfessionalWorkingHour
    {
        $professionalUnit = $workingHour->professionalUnit;

        WorkingHourOpeningHoursGuard::assertWithinOpeningHours($professionalUnit->unit, $workingHour->weekday, $workingHour->starts_at, $workingHour->ends_at);

        WorkingHourOverlapGuard::assertNoConflict(
            $professionalUnit,
            $workingHour->weekday,
            $workingHour->starts_at,
            $workingHour->ends_at,
            $workingHour->effective_from?->format('Y-m-d'),
            $workingHour->effective_until?->format('Y-m-d'),
            excludingId: $workingHour->id,
        );

        $workingHour->restore();
        $workingHour->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $workingHour,
            after: ['status' => $workingHour->status->value],
            organization: $professionalUnit->organization,
        );

        return $workingHour;
    }
}
