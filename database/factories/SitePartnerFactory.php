<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SitePartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePartner>
 */
class SitePartnerFactory extends Factory
{
    protected $model = SitePartner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'logo_path' => 'site-partners/placeholder.jpg',
            'url' => $this->faker->url(),
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
