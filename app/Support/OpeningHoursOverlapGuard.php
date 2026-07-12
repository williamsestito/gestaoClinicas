<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\Organization\OpeningHourData;
use Illuminate\Validation\ValidationException;

/**
 * Impede que dois intervalos de horário de funcionamento se sobreponham no
 * mesmo dia da semana. Usado tanto pelas Actions (defesa em profundidade)
 * quanto pelos Form Requests de organização/unidade.
 */
final class OpeningHoursOverlapGuard
{
    /** @param list<OpeningHourData> $openingHours */
    public static function assertNoOverlap(array $openingHours): void
    {
        $byDay = [];
        foreach ($openingHours as $hour) {
            $byDay[$hour->dayOfWeek][] = $hour;
        }

        foreach ($byDay as $day => $hours) {
            usort($hours, fn (OpeningHourData $a, OpeningHourData $b) => $a->opensAt <=> $b->opensAt);

            for ($i = 1; $i < count($hours); $i++) {
                if ($hours[$i]->opensAt < $hours[$i - 1]->closesAt) {
                    throw ValidationException::withMessages([
                        'opening_hours' => "Os horários informados para o dia {$day} se sobrepõem.",
                    ]);
                }
            }
        }
    }
}
