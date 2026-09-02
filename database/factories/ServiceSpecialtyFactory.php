<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSpecialty>
 */
class ServiceSpecialtyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'organization_id' => fn (array $attributes) => Service::query()->findOrFail((string) $attributes['service_id'])->organization_id,
            'specialty_id' => fn (array $attributes) => Specialty::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
        ];
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['deleted_at' => now()]);
    }
}
