<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentRequestStatus;
use App\Models\AppointmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentRequest>
 */
class AppointmentRequestFactory extends Factory
{
    protected $model = AppointmentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => $this->faker->numerify('(##) #####-####'),
            'email' => $this->faker->safeEmail(),
            'preferred_period' => $this->faker->randomElement(['Manhã', 'Tarde', 'Noite']),
            'status' => AppointmentRequestStatus::Pending,
            'terms_accepted_at' => now(),
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AppointmentRequestStatus::Contacted]);
    }
}
