<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Models\Professional;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Calcula, para uma janela de datas limitada, quais dias estão
 * operacionalmente disponíveis para um profissional numa unidade —
 * reaproveitando ProfessionalAvailabilityResolver dia a dia. Nunca
 * persiste a lista de datas: é sempre recalculada sob demanda, para uma
 * janela pequena (o próprio resumo de vigência e o calendário público usam
 * este serviço, nunca calculando períodos ilimitados).
 */
final class ProfessionalAvailabilityCalendarResolver
{
    /** Maior janela de dias aceita numa única chamada — evita cálculo ilimitado (ex.: "anos de disponibilidade"). */
    private const MAX_WINDOW_DAYS = 95;

    public function __construct(private readonly ProfessionalAvailabilityResolver $availabilityResolver) {}

    /**
     * @return Collection<int, array{date: string, is_operational: bool}>
     */
    public function resolveWindow(Professional $professional, Unit $unit, CarbonInterface $from, CarbonInterface $until): Collection
    {
        if ($from->gt($until)) {
            throw new InvalidArgumentException('A data inicial não pode ser posterior à data final.');
        }

        if ($from->diffInDays($until) > self::MAX_WINDOW_DAYS) {
            throw new InvalidArgumentException('A janela de cálculo não pode ultrapassar '.self::MAX_WINDOW_DAYS.' dias.');
        }

        $results = collect();
        $cursor = $from->copy();

        while ($cursor->lte($until)) {
            $daily = $this->availabilityResolver->resolve($professional, $unit, $cursor);

            $results->push([
                'date' => $cursor->toDateString(),
                'is_operational' => $daily->isOperational,
            ]);

            $cursor = $cursor->addDay();
        }

        return $results;
    }

    /**
     * Resumo de uma vigência recém-configurada: quantos dias no período,
     * quantos correspondem aos dias da semana selecionados, quantos ficam
     * efetivamente disponíveis (descontando funcionamento da unidade e
     * bloqueios) e quantas datas bloqueadas existem no período.
     *
     * @param  array<int, int>  $weekdays
     * @return array{total_days: int, selected_weekday_days: int, available_days: int, blocked_days: int}
     */
    public function summarizeVigency(Professional $professional, Unit $unit, CarbonInterface $from, CarbonInterface $until, array $weekdays): array
    {
        $window = $this->resolveWindow($professional, $unit, $from, $until);

        $totalDays = $window->count();
        $selectedWeekdayDays = 0;
        $availableDays = 0;
        $blockedDays = 0;

        foreach ($window as $day) {
            $date = Carbon::parse($day['date']);
            $isSelectedWeekday = in_array($date->dayOfWeek, $weekdays, true);

            if ($isSelectedWeekday) {
                $selectedWeekdayDays++;
            }

            if ($day['is_operational']) {
                $availableDays++;
            } elseif ($isSelectedWeekday) {
                $blockedDays++;
            }
        }

        return [
            'total_days' => $totalDays,
            'selected_weekday_days' => $selectedWeekdayDays,
            'available_days' => $availableDays,
            'blocked_days' => $blockedDays,
        ];
    }
}
