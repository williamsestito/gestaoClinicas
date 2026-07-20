<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteFaq>
 */
class SiteFaqFactory extends Factory
{
    protected $model = SiteFaq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence().'?',
            'answer' => $this->faker->paragraph(),
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
