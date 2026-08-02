<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalSpecialty>
 */
class ProfessionalSpecialtyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'organization_id' => fn (array $attributes) => Professional::query()->findOrFail((string) $attributes['professional_id'])->organization_id,
            'specialty_id' => fn (array $attributes) => Specialty::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'is_primary' => false,
            'status' => RecordStatus::Active,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
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
