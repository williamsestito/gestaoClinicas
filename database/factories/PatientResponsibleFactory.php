<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientResponsible;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientResponsible>
 */
class PatientResponsibleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'organization_id' => fn (array $attributes) => Patient::query()->findOrFail((string) $attributes['patient_id'])->organization_id,
            'name' => fake()->name(),
            'document' => LegalEntityFactory::validCpf(),
            'phone' => fake()->numerify('###########'),
            'relationship' => fake()->randomElement(['mãe', 'pai', 'avó', 'avô', 'tutor legal']),
            'is_legal_guardian' => true,
            'is_financial_responsible' => false,
            'is_authorized_representative' => false,
        ];
    }

    public function legalGuardian(): static
    {
        return $this->state(fn (array $attributes) => ['is_legal_guardian' => true]);
    }

    public function financialResponsible(): static
    {
        return $this->state(fn (array $attributes) => ['is_financial_responsible' => true]);
    }
}
