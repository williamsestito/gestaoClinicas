<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteProfessional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteProfessional>
 */
class SiteProfessionalFactory extends Factory
{
    protected $model = SiteProfessional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'role_title' => $this->faker->randomElement(['Dermatologista', 'Fisioterapeuta', 'Esteticista', 'Nutricionista']),
            'specialty' => $this->faker->words(2, true),
            'professional_register' => 'CRM/SC '.$this->faker->numerify('#####'),
            'bio' => $this->faker->paragraph(),
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
