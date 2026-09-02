<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role_id' => null,
            'invited_by' => User::factory(),
            'token_hash' => Hash::make(Str::random(40)),
            'status' => InvitationStatus::Pending,
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => InvitationStatus::Cancelled]);
    }
}
