<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LegalEntity;
use App\Models\ProfessionalService;
use App\Models\ProfessionalServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalServiceUnit>
 */
class ProfessionalServiceUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_service_id' => ProfessionalService::factory(),
            'organization_id' => fn (array $attributes) => ProfessionalService::query()
                ->findOrFail((string) $attributes['professional_service_id'])->organization_id,
            'unit_id' => fn (array $attributes) => Unit::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'legal_entity_id' => LegalEntity::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            ])->id,
        ];
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['deleted_at' => now()]);
    }
}
