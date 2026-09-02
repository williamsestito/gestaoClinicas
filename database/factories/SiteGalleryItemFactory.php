<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteGalleryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteGalleryItem>
 */
class SiteGalleryItemFactory extends Factory
{
    protected $model = SiteGalleryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image_path' => 'site-gallery/placeholder.jpg',
            'caption' => $this->faker->sentence(4),
            'alt_text' => $this->faker->sentence(4),
            'category' => $this->faker->randomElement(['Estrutura', 'Equipe', 'Equipamentos']),
            'is_cover' => false,
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function cover(): static
    {
        return $this->state(fn (array $attributes) => ['is_cover' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
