<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\SharedResource;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedResource>
 */
class SharedResourceFactory extends Factory
{
    protected $model = SharedResource::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'unit_id' => Unit::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => null,
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
