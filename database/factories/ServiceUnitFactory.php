<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LegalEntity;
use App\Models\Service;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceUnit>
 */
class ServiceUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'organization_id' => fn (array $attributes) => Service::query()->findOrFail((string) $attributes['service_id'])->organization_id,
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
