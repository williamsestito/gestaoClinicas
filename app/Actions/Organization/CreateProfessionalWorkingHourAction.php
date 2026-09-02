<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\WorkingHourOpeningHoursGuard;
use App\Support\Availability\WorkingHourOverlapGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProfessionalWorkingHourAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{weekday: int, starts_at: string, ends_at: string, effective_from: ?string, effective_until: ?string} $attributes */
    public function handle(ProfessionalUnit $professionalUnit, array $attributes): ProfessionalWorkingHour
    {
        $weekday = Weekday::from($attributes['weekday']);
        $unit = $professionalUnit->unit;

        if ($unit->trashed() || $unit->status !== RecordStatus::Active) {
            throw ValidationException::withMessages([
                'starts_at' => 'Não é possível cadastrar jornada porque a unidade não está mais ativa.',
            ]);
        }

        WorkingHourOpeningHoursGuard::assertWithinOpeningHours($unit, $weekday, $attributes['starts_at'], $attributes['ends_at']);

        try {
            $workingHour = DB::transaction(function () use ($professionalUnit, $weekday, $attributes) {
                WorkingHourOverlapGuard::assertNoConflict(
                    $professionalUnit,
                    $weekday,
                    $attributes['starts_at'],
                    $attributes['ends_at'],
                    $attributes['effective_from'],
                    $attributes['effective_until'],
                );

                return $professionalUnit->workingHours()->create([
                    'organization_id' => $professionalUnit->organization_id,
                    'weekday' => $weekday,
                    'starts_at' => $attributes['starts_at'],
                    'ends_at' => $attributes['ends_at'],
                    'effective_from' => $attributes['effective_from'],
                    'effective_until' => $attributes['effective_until'],
                    'status' => RecordStatus::Active,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'starts_at' => 'Este horário já está cadastrado para este dia.',
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->log(
                AuditAction::ConflictDetected,
                auditable: $professionalUnit,
                after: ['weekday' => $weekday->value, 'starts_at' => $attributes['starts_at'], 'ends_at' => $attributes['ends_at']],
                organization: $professionalUnit->organization,
            );

            throw $exception;
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $workingHour,
            after: $workingHour->only(['weekday', 'starts_at', 'ends_at', 'effective_from', 'effective_until', 'status']),
            organization: $professionalUnit->organization,
        );

        return $workingHour;
    }
}
