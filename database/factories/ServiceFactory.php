<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => 'SRV-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => null,
            'default_duration_minutes' => 30,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'default_price_cents' => fake()->numberBetween(5000, 50000),
            'status' => RecordStatus::Active,
            'color' => null,
            'is_public' => false,
            'requires_manual_confirmation' => false,
            'internal_notes' => null,
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

    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['is_public' => true]);
    }
}
