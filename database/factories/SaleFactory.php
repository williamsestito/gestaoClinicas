<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'organization_id' => fn (array $attributes) => Patient::query()->findOrFail((string) $attributes['patient_id'])->organization_id,
            'unit_id' => fn (array $attributes) => Unit::factory()->for(
                Organization::query()->findOrFail((string) $attributes['organization_id']),
            )->create()->id,
            'legal_entity_id' => fn (array $attributes) => LegalEntity::factory()->for(
                Organization::query()->findOrFail((string) $attributes['organization_id']),
            )->create()->id,
            'professional_id' => null,
            'appointment_id' => null,
            'status' => SaleStatus::Draft,
            'subtotal_cents' => 0,
            'discount_total_cents' => 0,
            'total_cents' => 0,
            'cancellation_reason' => null,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SaleStatus::Confirmed]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SaleStatus::PendingApproval]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleStatus::Cancelled,
            'cancellation_reason' => 'Cancelada em teste.',
        ]);
    }
}
