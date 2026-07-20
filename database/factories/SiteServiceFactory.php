<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteService>
 */
class SiteServiceFactory extends Factory
{
    protected $model = SiteService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['Estética', 'Saúde', 'Bem-estar']),
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90]),
            'starting_price_cents' => $this->faker->numberBetween(8000, 60000),
            'cta_text' => 'Agendar avaliação',
            'is_featured' => false,
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
