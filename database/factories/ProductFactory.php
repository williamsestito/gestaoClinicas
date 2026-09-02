<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => 'PRD-'.fake()->unique()->numberBetween(1000, 9999),
            'barcode' => null,
            'unit_of_measure' => 'un',
            'cost_cents' => fake()->numberBetween(1000, 5000),
            'margin_percentage' => 50,
            'price_cents' => fake()->numberBetween(2000, 10000),
            'max_discount_percentage' => 10,
            'status' => RecordStatus::Active,
            'internal_notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RecordStatus::Inactive]);
    }
}
