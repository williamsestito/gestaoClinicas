<?php

declare(strict_types=1);

namespace App\Data\Organization;

final readonly class OpeningHourData
{
    public function __construct(
        public int $dayOfWeek,
        public string $opensAt,
        public string $closesAt,
        public int $sortOrder = 0,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, int $sortOrder = 0): self
    {
        return new self(
            dayOfWeek: (int) $data['day_of_week'],
            opensAt: (string) $data['opens_at'],
            closesAt: (string) $data['closes_at'],
            sortOrder: $sortOrder,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'day_of_week' => $this->dayOfWeek,
            'opens_at' => $this->opensAt,
            'closes_at' => $this->closesAt,
            'sort_order' => $this->sortOrder,
        ];
    }
}
