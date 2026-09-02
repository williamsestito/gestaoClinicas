<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalWorkingHour>
 */
class ProfessionalWorkingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_unit_id' => ProfessionalUnit::factory(),
            'organization_id' => fn (array $attributes) => ProfessionalUnit::query()->findOrFail((string) $attributes['professional_unit_id'])->organization_id,
            'weekday' => Weekday::Monday,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'effective_from' => null,
            'effective_until' => null,
            'status' => RecordStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RecordStatus::Inactive]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['deleted_at' => now()]);
    }
}
