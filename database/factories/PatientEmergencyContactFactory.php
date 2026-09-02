<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientEmergencyContact>
 */
class PatientEmergencyContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'organization_id' => fn (array $attributes) => Patient::query()->findOrFail((string) $attributes['patient_id'])->organization_id,
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['mãe', 'pai', 'cônjuge', 'amigo(a)']),
            'phone_primary' => fake()->numerify('###########'),
            'phone_secondary' => null,
        ];
    }
}
