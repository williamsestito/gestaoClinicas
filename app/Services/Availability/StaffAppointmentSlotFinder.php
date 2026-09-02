<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sugestão de horários livres para o staff/portal criar um agendamento —
 * mesmo algoritmo de fatiamento de
 * App\Services\Availability\PublicAvailabilityFinder::availableTimes(),
 * mas subtraindo também os horários já ocupados do profissional naquele
 * dia (App\Services\Availability\BookedRangeResolver — agendamentos reais
 * e pré-agendamentos pendentes). Nunca autoritativo: a Action de criação
 * sempre revalida com App\Support\Availability\AppointmentOverlapGuard.
 */
final class StaffAppointmentSlotFinder
{
    public function __construct(
        private readonly ProfessionalAvailabilityResolver $availabilityResolver,
        private readonly BookedRangeResolver $bookedRangeResolver,
    ) {}

    /** @return Collection<int, array{time: string, duration_minutes: int}> */
    public function availableTimes(Professional $professional, Unit $unit, ProfessionalService $link, CarbonInterface $date): Collection
    {
        $duration = $link->effectiveDurationMinutes();
        $bufferBefore = $link->effectiveBufferBeforeMinutes();
        $bufferAfter = $link->effectiveBufferAfterMinutes();
        $totalSpan = $bufferBefore + $duration + $bufferAfter;

        $daily = $this->availabilityResolver->resolve($professional, $unit, $date);
        $bookedRanges = $this->bookedRangeResolver->forProfessionalOnDate($professional, $unit, $date);

        $slots = collect();

        foreach ($daily->effectiveIntervals as $interval) {
            $cursor = $interval->startsAt;

            while ($this->addMinutes($cursor, $totalSpan) <= $interval->endsAt) {
                $slotStart = $this->addMinutes($cursor, $bufferBefore);
                $slotEnd = $this->addMinutes($slotStart, $duration);

                if (! BookedRangeResolver::overlapsAny($slotStart, $slotEnd, $bookedRanges)) {
                    $slots->push(['time' => $slotStart, 'duration_minutes' => $duration]);
                }

                $cursor = $this->addMinutes($cursor, $duration);
            }
        }

        return $slots->values();
    }

    private function addMinutes(string $time, int $minutes): string
    {
        return Carbon::createFromFormat('H:i', $time === '24:00' ? '23:59' : $time)
            ->addMinutes($minutes)
            ->format('H:i');
    }
}
