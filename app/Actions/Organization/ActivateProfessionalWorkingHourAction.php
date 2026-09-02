<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalWorkingHour;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\WorkingHourOpeningHoursGuard;
use App\Support\Availability\WorkingHourOverlapGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reativar um intervalo pode reintroduzir um conflito criado enquanto ele
 * estava inativo — revalida sobreposição e funcionamento da unidade antes
 * de ativar, mesma defesa usada na criação.
 */
class ActivateProfessionalWorkingHourAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalWorkingHour $workingHour): ProfessionalWorkingHour
    {
        $professionalUnit = $workingHour->professionalUnit;

        if ($professionalUnit->unit->trashed() || $professionalUnit->unit->status !== RecordStatus::Active) {
            throw ValidationException::withMessages([
                'starts_at' => 'Não é possível ativar porque a unidade não está mais ativa.',
            ]);
        }

        WorkingHourOpeningHoursGuard::assertWithinOpeningHours($professionalUnit->unit, $workingHour->weekday, $workingHour->starts_at, $workingHour->ends_at);

        DB::transaction(function () use ($workingHour, $professionalUnit) {
            WorkingHourOverlapGuard::assertNoConflict(
                $professionalUnit,
                $workingHour->weekday,
                $workingHour->starts_at,
                $workingHour->ends_at,
                $workingHour->effective_from?->format('Y-m-d'),
                $workingHour->effective_until?->format('Y-m-d'),
                excludingId: $workingHour->id,
            );

            $workingHour->update(['status' => RecordStatus::Active]);
        });

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $workingHour,
            before: ['status' => RecordStatus::Inactive->value],
            after: ['status' => RecordStatus::Active->value],
            organization: $professionalUnit->organization,
        );

        return $workingHour;
    }
}
