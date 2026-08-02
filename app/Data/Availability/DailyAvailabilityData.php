<?php

declare(strict_types=1);

namespace App\Data\Availability;

/**
 * Resultado do cálculo de disponibilidade operacional de um profissional
 * em uma unidade, para uma data específica — derivado sob demanda,
 * nunca persistido (ver App\Services\Availability\ProfessionalAvailabilityResolver).
 */
final readonly class DailyAvailabilityData
{
    /**
     * @param  array<int, DailyAvailabilityInterval>  $regularIntervals  Jornada regular, antes de bloqueios.
     * @param  array<int, DailyAvailabilityInterval>  $effectiveIntervals  Jornada regular menos bloqueios.
     * @param  array<int, array{id: string, type: string}>  $applicableTimeBlocks
     * @param  array<int, string>  $reasons  Motivos técnicos de indisponibilidade total.
     * @param  array<int, string>  $warnings  Alertas de configuração (não impedem o cálculo).
     */
    public function __construct(
        public bool $isOperational,
        public string $timezone,
        public int $weekday,
        public array $regularIntervals,
        public array $effectiveIntervals,
        public array $applicableTimeBlocks,
        public array $reasons,
        public array $warnings,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'is_operational' => $this->isOperational,
            'timezone' => $this->timezone,
            'weekday' => $this->weekday,
            'regular_intervals' => array_map(fn (DailyAvailabilityInterval $interval) => $interval->toArray(), $this->regularIntervals),
            'effective_intervals' => array_map(fn (DailyAvailabilityInterval $interval) => $interval->toArray(), $this->effectiveIntervals),
            'applicable_time_blocks' => $this->applicableTimeBlocks,
            'reasons' => $this->reasons,
            'warnings' => $this->warnings,
        ];
    }
}
