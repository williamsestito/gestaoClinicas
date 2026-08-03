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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configura, em uma única operação atômica, a jornada recorrente de um
 * profissional numa unidade: um conjunto de dias da semana, um ou mais
 * intervalos aplicados a todos esses dias, e uma vigência. Pensada para o
 * fluxo guiado de "Minha agenda" (configuração inicial rápida) — o
 * cadastro dia a dia mais granular continua disponível via
 * CreateProfessionalWorkingHourAction. Todos os dias/intervalos são
 * validados antes de qualquer escrita: se qualquer combinação conflitar,
 * nada é salvo.
 */
class ConfigureProfessionalWorkingHoursAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{weekdays: array<int, int>, intervals: array<int, array{starts_at: string, ends_at: string}>, effective_from: string, effective_until: string}  $attributes
     * @return Collection<int, ProfessionalWorkingHour>
     */
    public function handle(ProfessionalUnit $professionalUnit, array $attributes): Collection
    {
        $unit = $professionalUnit->unit;

        if ($unit->trashed() || $unit->status !== RecordStatus::Active) {
            throw ValidationException::withMessages([
                'weekdays' => 'Não é possível configurar jornada porque a unidade não está mais ativa.',
            ]);
        }

        $this->assertNoInternalOverlap($attributes['intervals']);

        $weekdays = array_map(fn (int $value) => Weekday::from($value), $attributes['weekdays']);

        $created = DB::transaction(function () use ($professionalUnit, $unit, $weekdays, $attributes) {
            $conflictsByDay = [];

            foreach ($weekdays as $weekday) {
                foreach ($attributes['intervals'] as $interval) {
                    try {
                        WorkingHourOpeningHoursGuard::assertWithinOpeningHours($unit, $weekday, $interval['starts_at'], $interval['ends_at']);
                        WorkingHourOverlapGuard::assertNoConflict(
                            $professionalUnit,
                            $weekday,
                            $interval['starts_at'],
                            $interval['ends_at'],
                            $attributes['effective_from'],
                            $attributes['effective_until'],
                        );
                    } catch (ValidationException $exception) {
                        $conflictsByDay[$weekday->label()] = collect($exception->errors())->flatten()->first();
                    }
                }
            }

            if ($conflictsByDay !== []) {
                $this->auditLogger->log(
                    AuditAction::ConflictDetected,
                    auditable: $professionalUnit,
                    after: ['weekdays' => $attributes['weekdays'], 'conflicts' => $conflictsByDay],
                    organization: $professionalUnit->organization,
                );

                throw ValidationException::withMessages([
                    'weekdays' => collect($conflictsByDay)
                        ->map(fn (?string $message, string $day) => "{$day}: {$message}")
                        ->values()
                        ->all(),
                ]);
            }

            $rows = collect();

            foreach ($weekdays as $weekday) {
                foreach ($attributes['intervals'] as $interval) {
                    $rows->push($professionalUnit->workingHours()->create([
                        'organization_id' => $professionalUnit->organization_id,
                        'weekday' => $weekday,
                        'starts_at' => $interval['starts_at'],
                        'ends_at' => $interval['ends_at'],
                        'effective_from' => $attributes['effective_from'],
                        'effective_until' => $attributes['effective_until'],
                        'status' => RecordStatus::Active,
                    ]));
                }
            }

            return $rows;
        });

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $professionalUnit,
            after: [
                'weekdays' => $attributes['weekdays'],
                'intervals_count' => count($attributes['intervals']),
                'effective_from' => $attributes['effective_from'],
                'effective_until' => $attributes['effective_until'],
                'includes_saturday' => in_array(Weekday::Saturday->value, $attributes['weekdays'], true),
                'includes_sunday' => in_array(Weekday::Sunday->value, $attributes['weekdays'], true),
                'created_count' => $created->count(),
            ],
            organization: $professionalUnit->organization,
        );

        return $created;
    }

    /** @param  array<int, array{starts_at: string, ends_at: string}>  $intervals */
    private function assertNoInternalOverlap(array $intervals): void
    {
        $seen = [];

        foreach ($intervals as $interval) {
            foreach ($seen as [$start, $end]) {
                if ($interval['starts_at'] < $end && $start < $interval['ends_at']) {
                    throw ValidationException::withMessages([
                        'intervals' => 'Os intervalos informados se sobrepõem entre si.',
                    ]);
                }
            }

            $seen[] = [$interval['starts_at'], $interval['ends_at']];
        }
    }
}
