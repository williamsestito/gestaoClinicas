<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\Weekday;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * Garante que um intervalo de jornada esteja contido em algum período de
 * funcionamento da unidade (App\Models\UnitOpeningHour) no mesmo dia da
 * semana. Não altera o horário informado automaticamente — apenas bloqueia
 * e informa quando está fora do funcionamento.
 */
final class WorkingHourOpeningHoursGuard
{
    public static function assertWithinOpeningHours(Unit $unit, Weekday $weekday, string $startsAt, string $endsAt): void
    {
        $containedInAnyPeriod = $unit->openingHours()
            ->where('day_of_week', $weekday->value)
            ->where('opens_at', '<=', $startsAt)
            ->where('closes_at', '>=', $endsAt)
            ->exists();

        if (! $containedInAnyPeriod) {
            throw ValidationException::withMessages([
                'starts_at' => 'O horário informado está fora do funcionamento da unidade.',
            ]);
        }
    }

    /**
     * Verifica se um intervalo já persistido continua compatível com o
     * funcionamento atual da unidade — usado para sinalizar inconsistência
     * quando a unidade altera seus horários posteriormente, sem apagar a
     * jornada nem alterá-la automaticamente.
     */
    public static function isWithinOpeningHours(Unit $unit, Weekday $weekday, string $startsAt, string $endsAt): bool
    {
        return $unit->openingHours()
            ->where('day_of_week', $weekday->value)
            ->where('opens_at', '<=', $startsAt)
            ->where('closes_at', '>=', $endsAt)
            ->exists();
    }
}
