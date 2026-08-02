<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalUnit>
 */
class ProfessionalUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'organization_id' => fn (array $attributes) => Professional::query()->findOrFail((string) $attributes['professional_id'])->organization_id,
            'unit_id' => fn (array $attributes) => Unit::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'legal_entity_id' => LegalEntity::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            ])->id,
            'is_primary' => false,
            'status' => RecordStatus::Active,
            'starts_on' => null,
            'ends_on' => null,
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
