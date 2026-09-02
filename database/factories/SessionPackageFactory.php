<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\SessionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionPackage>
 */
class SessionPackageFactory extends Factory
{
    protected $model = SessionPackage::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'patient_id' => Patient::factory(),
            'service_id' => null,
            'total_sessions' => 10,
            'expires_at' => null,
            'status' => RecordStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RecordStatus::Inactive]);
    }
}
