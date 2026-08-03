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
 * Copia os intervalos ativos de um dia de origem para um ou mais dias de
 * destino, sempre dentro da mesma unidade — cópia entre unidades diferentes
 * não é implementada nesta fase, pois exigiria a mesma checagem de conflito
 * entre unidades já usada na criação, tornando a operação equivalente a
 * criar manualmente em cada unidade (nenhuma regra segura identificada para
 * simplificar isso). Todos os destinos são validados antes de qualquer
 * escrita: se qualquer um for inválido, nada é salvo.
 */
class CopyProfessionalWorkingHoursAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{source_weekday: int, target_weekdays: array<int, int>}  $attributes
     * @return Collection<int, ProfessionalWorkingHour>
     */
    public function handle(ProfessionalUnit $professionalUnit, array $attributes): Collection
    {
        $sourceWeekday = Weekday::from($attributes['source_weekday']);
        $targetWeekdays = array_map(fn (int $value) => Weekday::from($value), $attributes['target_weekdays']);

        $sourceIntervals = $professionalUnit->workingHours()
            ->where('weekday', $sourceWeekday->value)
            ->where('status', RecordStatus::Active)
            ->get();

        if ($sourceIntervals->isEmpty()) {
            throw ValidationException::withMessages([
                'source_weekday' => 'Não há horários ativos neste dia para copiar.',
            ]);
        }

        $created = DB::transaction(function () use ($professionalUnit, $sourceIntervals, $targetWeekdays) {
            $conflictsByDay = [];

            foreach ($targetWeekdays as $targetWeekday) {
                foreach ($sourceIntervals as $interval) {
                    try {
                        WorkingHourOpeningHoursGuard::assertWithinOpeningHours($professionalUnit->unit, $targetWeekday, $interval->starts_at, $interval->ends_at);
                        WorkingHourOverlapGuard::assertNoConflict(
                            $professionalUnit,
                            $targetWeekday,
                            $interval->starts_at,
                            $interval->ends_at,
                            $interval->effective_from?->format('Y-m-d'),
                            $interval->effective_until?->format('Y-m-d'),
                        );
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first();
                        $conflictsByDay[$targetWeekday->label()] = $message;
                    }
                }
            }

            if ($conflictsByDay !== []) {
                // Uma chave por dia (não um único "target_weekdays" com
                // várias mensagens) — o Inertia só repassa a primeira
                // mensagem de cada chave ao frontend, então agrupar tudo
                // numa chave só faz o motivo dos demais dias em conflito
                // desaparecer silenciosamente.
                throw ValidationException::withMessages(
                    collect($conflictsByDay)
                        ->mapWithKeys(fn (?string $message, string $day) => ["target_weekdays.{$day}" => "{$day}: {$message}"])
                        ->all()
                );
            }

            $created = collect();

            foreach ($targetWeekdays as $targetWeekday) {
                foreach ($sourceIntervals as $interval) {
                    $created->push($professionalUnit->workingHours()->create([
                        'organization_id' => $professionalUnit->organization_id,
                        'weekday' => $targetWeekday,
                        'starts_at' => $interval->starts_at,
                        'ends_at' => $interval->ends_at,
                        'effective_from' => $interval->effective_from,
                        'effective_until' => $interval->effective_until,
                        'status' => RecordStatus::Active,
                    ]));
                }
            }

            return $created;
        });

        $this->auditLogger->log(
            AuditAction::Copied,
            auditable: $professionalUnit,
            after: [
                'source_weekday' => $sourceWeekday->value,
                'target_weekdays' => $attributes['target_weekdays'],
                'count' => $created->count(),
            ],
            organization: $professionalUnit->organization,
        );

        return $created;
    }
}
