<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteBenefit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteBenefit>
 */
class SiteBenefitFactory extends Factory
{
    protected $model = SiteBenefit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icon' => 'heart-pulse',
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
