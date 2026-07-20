<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteTestimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteTestimonial>
 */
class SiteTestimonialFactory extends Factory
{
    protected $model = SiteTestimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_name' => $this->faker->firstName().' '.$this->faker->randomLetter().'.',
            'rating' => $this->faker->numberBetween(4, 5),
            'content' => $this->faker->paragraph(),
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
