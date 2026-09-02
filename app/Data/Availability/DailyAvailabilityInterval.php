<?php

declare(strict_types=1);

namespace App\Data\Availability;

/** Intervalo de disponibilidade em hora civil local, formato H:i. */
final readonly class DailyAvailabilityInterval
{
    public function __construct(
        public string $startsAt,
        public string $endsAt,
    ) {}

    /** @return array{starts_at: string, ends_at: string} */
    public function toArray(): array
    {
        return ['starts_at' => $this->startsAt, 'ends_at' => $this->endsAt];
    }
}
