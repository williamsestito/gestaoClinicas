<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Data\Availability\DailyAvailabilityData;
use App\Data\Availability\DailyAvailabilityInterval;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use App\Models\Unit;
use App\Support\Availability\LocalTimeConverter;
use App\Support\Availability\WorkingHourOpeningHoursGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deriva a disponibilidade operacional de um profissional em uma unidade,
 * para uma data específica, a partir de:
 *
 *   profissional ativo
 *   + vínculo profissional-unidade ativo e vigente
 *   + unidade ativa
 *   + jornada regular ativa e vigente
 *   - ausências e bloqueios ativos
 *   = disponibilidade operacional
 *
 * Nunca gera nem persiste slots — apenas responde, sob demanda, para uma
 * única data. Sem consultas de agenda, reservas ou pacientes (não existem
 * nesta fase).
 */
final class ProfessionalAvailabilityResolver
{
    public function resolve(Professional $professional, Unit $unit, CarbonInterface $date): DailyAvailabilityData
    {
        $reasons = [];
        $warnings = [];

        if ($professional->status !== RecordStatus::Active || $professional->trashed()) {
            $reasons[] = 'Profissional inativo.';
        }

        if ($unit->trashed() || $unit->status !== RecordStatus::Active) {
            $reasons[] = 'Unidade inativa.';
        }

        $link = $professional->unitLinks()
            ->where('unit_id', $unit->id)
            ->where('status', RecordStatus::Active)
            ->first();

        if ($link === null) {
            $reasons[] = 'Sem vínculo ativo com a unidade.';
        } elseif ($link->starts_on !== null && $link->starts_on->gt($date)) {
            $reasons[] = 'Vínculo com a unidade ainda não vigente nesta data.';
            $link = null;
        } elseif ($link->ends_on !== null && $link->ends_on->lt($date)) {
            $reasons[] = 'Vínculo com a unidade já encerrado nesta data.';
            $link = null;
        }

        $weekday = Weekday::from($date->dayOfWeek);
        $regularIntervals = [];

        if ($link !== null) {
            $workingHours = $link->workingHours()
                ->where('weekday', $weekday->value)
                ->where('status', RecordStatus::Active)
                ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString()))
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date->toDateString()))
                ->get();

            if ($workingHours->isEmpty()) {
                $reasons[] = 'Sem jornada configurada para este dia.';
            }

            foreach ($workingHours as $workingHour) {
                if (! WorkingHourOpeningHoursGuard::isWithinOpeningHours($unit, $weekday, $workingHour->starts_at, $workingHour->ends_at)) {
                    $warnings[] = "O horário de {$workingHour->starts_at} às {$workingHour->ends_at} está fora do funcionamento atual da unidade.";

                    continue;
                }

                $regularIntervals[] = new DailyAvailabilityInterval(substr($workingHour->starts_at, 0, 5), substr($workingHour->ends_at, 0, 5));
            }
        }

        $applicableTimeBlocks = $this->applicableTimeBlocks($professional, $unit, $date);
        $effectiveIntervals = $this->subtractBlocks($regularIntervals, $applicableTimeBlocks, $unit->timezone, $date);

        if ($regularIntervals !== [] && $effectiveIntervals === []) {
            $reasons[] = 'Toda a jornada deste dia está coberta por ausências ou bloqueios.';
        }

        $isOperational = $reasons === [] && $effectiveIntervals !== [];

        return new DailyAvailabilityData(
            isOperational: $isOperational,
            timezone: $unit->timezone,
            weekday: $weekday->value,
            regularIntervals: $regularIntervals,
            effectiveIntervals: $effectiveIntervals,
            applicableTimeBlocks: $applicableTimeBlocks->map(fn (ProfessionalTimeBlock $block) => ['id' => $block->id, 'type' => $block->type->value])->values()->all(),
            reasons: $reasons,
            warnings: $warnings,
        );
    }

    /** @return Collection<int, ProfessionalTimeBlock> */
    private function applicableTimeBlocks(Professional $professional, Unit $unit, CarbonInterface $date): Collection
    {
        $dayStart = LocalTimeConverter::startOfLocalDayInUtc($date->toDateString(), $unit->timezone);
        $dayEnd = LocalTimeConverter::startOfNextLocalDayInUtc($date->toDateString(), $unit->timezone);

        return $professional->timeBlocks()
            ->where('status', RecordStatus::Active)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get()
            ->filter(fn (ProfessionalTimeBlock $block) => $block->coversUnit($unit->id))
            ->values();
    }

    /**
     * @param  array<int, DailyAvailabilityInterval>  $regularIntervals
     * @param  Collection<int, ProfessionalTimeBlock>  $timeBlocks
     * @return array<int, DailyAvailabilityInterval>
     */
    private function subtractBlocks(array $regularIntervals, Collection $timeBlocks, string $timezone, CarbonInterface $date): array
    {
        if ($regularIntervals === [] || $timeBlocks->isEmpty()) {
            return $regularIntervals;
        }

        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        /** @var array<int, array{0: string, 1: string}> $blockedRanges */
        $blockedRanges = [];

        foreach ($timeBlocks as $block) {
            $localStart = $block->starts_at->copy()->setTimezone($timezone);
            $localEnd = $block->ends_at->copy()->setTimezone($timezone);

            $clippedStart = $localStart->lt($dayStart) ? $dayStart : $localStart;
            $clippedEnd = $localEnd->gt($dayEnd) ? $dayEnd : $localEnd;

            if ($clippedStart->gte($clippedEnd)) {
                continue;
            }

            $blockedRanges[] = [
                $clippedStart->format('H:i'),
                $clippedEnd->eq($dayEnd) ? '24:00' : $clippedEnd->format('H:i'),
            ];
        }

        $result = [];

        foreach ($regularIntervals as $interval) {
            $pieces = [[$interval->startsAt, $interval->endsAt]];

            foreach ($blockedRanges as [$blockStart, $blockEnd]) {
                $next = [];

                foreach ($pieces as [$pieceStart, $pieceEnd]) {
                    if ($blockEnd <= $pieceStart || $blockStart >= $pieceEnd) {
                        $next[] = [$pieceStart, $pieceEnd];

                        continue;
                    }

                    if ($blockStart > $pieceStart) {
                        $next[] = [$pieceStart, $blockStart];
                    }

                    if ($blockEnd < $pieceEnd) {
                        $next[] = [$blockEnd, $pieceEnd];
                    }
                }

                $pieces = $next;
            }

            foreach ($pieces as [$startsAt, $endsAt]) {
                $result[] = new DailyAvailabilityInterval($startsAt, $endsAt);
            }
        }

        return $result;
    }
}
