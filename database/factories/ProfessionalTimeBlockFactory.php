<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\ProfessionalTimeBlockType;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalTimeBlock>
 */
class ProfessionalTimeBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'organization_id' => fn (array $attributes) => Professional::query()->findOrFail((string) $attributes['professional_id'])->organization_id,
            'unit_id' => null,
            'type' => ProfessionalTimeBlockType::Vacation,
            'scope' => ProfessionalTimeBlockScope::AllUnits,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(20),
            'is_all_day' => true,
            'reason' => 'Férias programadas',
            'internal_notes' => null,
            'status' => RecordStatus::Active,
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
}
