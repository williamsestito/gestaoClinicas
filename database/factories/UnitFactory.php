<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->streetName().' Clínica';

        return [
            'organization_id' => Organization::factory(),
            'legal_entity_id' => LegalEntity::factory(),
            'name' => $name,
            'code' => 'UN-'.fake()->unique()->numberBetween(1000, 9999),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => RecordStatus::Active,
            'is_headquarters' => false,
            'timezone' => 'America/Sao_Paulo',
        ];
    }

    public function headquarters(): static
    {
        return $this->state(fn (array $attributes) => ['is_headquarters' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RecordStatus::Inactive]);
    }
}
