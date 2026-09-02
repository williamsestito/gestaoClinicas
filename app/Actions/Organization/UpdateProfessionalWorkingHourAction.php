<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\Weekday;
use App\Models\ProfessionalWorkingHour;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\WorkingHourOpeningHoursGuard;
use App\Support\Availability\WorkingHourOverlapGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProfessionalWorkingHourAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{weekday: int, starts_at: string, ends_at: string, effective_from: ?string, effective_until: ?string} $attributes */
    public function handle(ProfessionalWorkingHour $workingHour, array $attributes): ProfessionalWorkingHour
    {
        $weekday = Weekday::from($attributes['weekday']);
        $professionalUnit = $workingHour->professionalUnit;

        WorkingHourOpeningHoursGuard::assertWithinOpeningHours($professionalUnit->unit, $weekday, $attributes['starts_at'], $attributes['ends_at']);

        $before = $workingHour->only(['weekday', 'starts_at', 'ends_at', 'effective_from', 'effective_until']);

        try {
            DB::transaction(function () use ($workingHour, $professionalUnit, $weekday, $attributes) {
                WorkingHourOverlapGuard::assertNoConflict(
                    $professionalUnit,
                    $weekday,
                    $attributes['starts_at'],
                    $attributes['ends_at'],
                    $attributes['effective_from'],
                    $attributes['effective_until'],
                    excludingId: $workingHour->id,
                );

                $workingHour->update([
                    'weekday' => $weekday,
                    'starts_at' => $attributes['starts_at'],
                    'ends_at' => $attributes['ends_at'],
                    'effective_from' => $attributes['effective_from'],
                    'effective_until' => $attributes['effective_until'],
                ]);
            });
        } catch (ValidationException $exception) {
            $this->auditLogger->log(
                AuditAction::ConflictDetected,
                auditable: $workingHour,
                after: ['weekday' => $weekday->value, 'starts_at' => $attributes['starts_at'], 'ends_at' => $attributes['ends_at']],
                organization: $professionalUnit->organization,
            );

            throw $exception;
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $workingHour,
            before: $before,
            after: $workingHour->only(['weekday', 'starts_at', 'ends_at', 'effective_from', 'effective_until']),
            organization: $professionalUnit->organization,
        );

        return $workingHour;
    }
}
