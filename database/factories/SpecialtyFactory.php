<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => null,
            'description' => null,
            'status' => RecordStatus::Active,
            'display_order' => 0,
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
