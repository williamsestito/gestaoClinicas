<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\Unit;
use App\Support\Availability\LocalTimeConverter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sugestão de horários livres para o staff criar um agendamento — mesmo
 * algoritmo de fatiamento de
 * App\Services\Availability\PublicAvailabilityFinder::availableTimes(),
 * mas subtraindo também os agendamentos já existentes do profissional
 * naquele dia (o buscador público não precisa disso porque não existia
 * reserva real quando foi escrito). Nunca autoritativo: a Action de
 * criação sempre revalida com App\Support\Availability\AppointmentOverlapGuard.
 */
final class StaffAppointmentSlotFinder
{
    /** @var list<AppointmentStatus> Estados que efetivamente ocupam o horário. */
    private const BLOCKING_STATUSES = [
        AppointmentStatus::Requested,
        AppointmentStatus::AwaitingConfirmation,
        AppointmentStatus::Confirmed,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::InProgress,
    ];

    public function __construct(private readonly ProfessionalAvailabilityResolver $availabilityResolver) {}

    /** @return Collection<int, array{time: string, duration_minutes: int}> */
    public function availableTimes(Professional $professional, Unit $unit, ProfessionalService $link, CarbonInterface $date): Collection
    {
        $duration = $link->effectiveDurationMinutes();
        $bufferBefore = $link->effectiveBufferBeforeMinutes();
        $bufferAfter = $link->effectiveBufferAfterMinutes();
        $totalSpan = $bufferBefore + $duration + $bufferAfter;

        $daily = $this->availabilityResolver->resolve($professional, $unit, $date);
        $bookedRanges = $this->bookedLocalRanges($professional, $unit, $date);

        $slots = collect();

        foreach ($daily->effectiveIntervals as $interval) {
            $cursor = $interval->startsAt;

            while ($this->addMinutes($cursor, $totalSpan) <= $interval->endsAt) {
                $slotStart = $this->addMinutes($cursor, $bufferBefore);
                $slotEnd = $this->addMinutes($slotStart, $duration);

                if (! $this->overlapsAny($slotStart, $slotEnd, $bookedRanges)) {
                    $slots->push(['time' => $slotStart, 'duration_minutes' => $duration]);
                }

                $cursor = $this->addMinutes($cursor, $duration);
            }
        }

        return $slots->values();
    }

    /** @return array<int, array{string, string}> */
    private function bookedLocalRanges(Professional $professional, Unit $unit, CarbonInterface $date): array
    {
        $dayStart = LocalTimeConverter::startOfLocalDayInUtc($date->toDateString(), $unit->timezone);
        $dayEnd = LocalTimeConverter::startOfNextLocalDayInUtc($date->toDateString(), $unit->timezone);

        return Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', array_map(fn (AppointmentStatus $status) => $status->value, self::BLOCKING_STATUSES))
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment) => [
                $appointment->starts_at->copy()->setTimezone($unit->timezone)->format('H:i'),
                $appointment->ends_at->copy()->setTimezone($unit->timezone)->format('H:i'),
            ])
            ->values()
            ->all();
    }

    /** @param  array<int, array{string, string}>  $ranges */
    private function overlapsAny(string $start, string $end, array $ranges): bool
    {
        foreach ($ranges as [$bookedStart, $bookedEnd]) {
            if ($start < $bookedEnd && $end > $bookedStart) {
                return true;
            }
        }

        return false;
    }

    private function addMinutes(string $time, int $minutes): string
    {
        return Carbon::createFromFormat('H:i', $time === '24:00' ? '23:59' : $time)
            ->addMinutes($minutes)
            ->format('H:i');
    }
}
