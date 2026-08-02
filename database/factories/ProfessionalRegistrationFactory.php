<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Support\Documents\BrazilianState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalRegistration>
 */
class ProfessionalRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'organization_id' => fn (array $attributes) => Professional::query()->findOrFail((string) $attributes['professional_id'])->organization_id,
            'council' => fake()->randomElement(['CRM', 'CRO', 'COREN', 'CREFITO', 'CRP', 'CRN']),
            'registration_type' => null,
            'registration_number' => (string) fake()->unique()->numberBetween(10000, 999999),
            'state' => fake()->randomElement(BrazilianState::cases()),
            'issued_at' => fake()->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            'expires_at' => null,
            'status' => RecordStatus::Active,
            'is_primary' => false,
            'internal_notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RecordStatus::Inactive]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['deleted_at' => now()]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }
}
