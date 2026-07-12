<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Unit;
use App\Models\UnitOpeningHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOpeningHour>
 */
class UnitOpeningHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'unit_id' => Unit::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'opens_at' => '08:00:00',
            'closes_at' => '18:00:00',
            'sort_order' => 0,
        ];
    }
}
