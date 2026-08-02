<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalService>
 */
class ProfessionalServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'organization_id' => fn (array $attributes) => Professional::query()->findOrFail((string) $attributes['professional_id'])->organization_id,
            'service_id' => fn (array $attributes) => Service::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'custom_duration_minutes' => null,
            'custom_price_cents' => null,
            'custom_buffer_before_minutes' => null,
            'custom_buffer_after_minutes' => null,
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
