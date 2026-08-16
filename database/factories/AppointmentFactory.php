<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'unit_id' => fn (array $attributes) => Unit::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'professional_id' => fn (array $attributes) => Professional::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'patient_id' => fn (array $attributes) => Patient::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'service_id' => fn (array $attributes) => Service::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(9, 30),
            'status' => AppointmentStatus::Confirmed,
            'cancellation_reason' => null,
            'checked_in_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'notes' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => 'Motivo de teste',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'checked_in_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
