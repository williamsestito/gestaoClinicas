<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'preferred_unit_id' => null,
            'primary_professional_id' => null,
            'name' => fake()->name(),
            'preferred_name' => null,
            'document' => LegalEntityFactory::validCpf(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'phone' => fake()->numerify('###########'),
            'whatsapp' => null,
            'email' => fake()->unique()->safeEmail(),
            'origin' => null,
            'photo_path' => null,
            'status' => RecordStatus::Active,
        ];
    }

    /** Menor de 18 anos — RN-004 exige responsável legal ativo. */
    public function minor(): static
    {
        return $this->state(fn (array $attributes) => [
            'birth_date' => fake()->dateTimeBetween('-17 years', '-1 years')->format('Y-m-d'),
            'document' => null,
        ]);
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
