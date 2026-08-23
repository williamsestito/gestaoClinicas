<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory()->completed(),
            'organization_id' => fn (array $attributes) => Appointment::query()->findOrFail((string) $attributes['appointment_id'])->organization_id,
            'unit_id' => fn (array $attributes) => Appointment::query()->findOrFail((string) $attributes['appointment_id'])->unit_id,
            'patient_id' => fn (array $attributes) => Appointment::query()->findOrFail((string) $attributes['appointment_id'])->patient_id,
            'professional_id' => fn (array $attributes) => Appointment::query()->findOrFail((string) $attributes['appointment_id'])->professional_id,
            'status' => MedicalRecordStatus::Draft,
            'anamnesis' => fake()->sentence(),
            'evaluation' => fake()->sentence(),
            'has_return_right' => false,
            'return_window_days' => null,
            'finalized_at' => null,
            'released_to_patient_at' => null,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MedicalRecordStatus::Finalized,
            'finalized_at' => now(),
        ]);
    }

    public function releasedToPatient(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MedicalRecordStatus::Finalized,
            'finalized_at' => now(),
            'released_to_patient_at' => now(),
        ]);
    }

    public function withReturnRight(int $windowDays = 15): static
    {
        return $this->state(fn (array $attributes) => [
            'has_return_right' => true,
            'return_window_days' => $windowDays,
        ]);
    }
}
