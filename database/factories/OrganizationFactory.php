<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => OrganizationStatus::Active,
            'default_timezone' => 'America/Sao_Paulo',
            'default_currency' => 'BRL',
            'locale' => 'pt_BR',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrganizationStatus::Suspended]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrganizationStatus::Inactive]);
    }
}
