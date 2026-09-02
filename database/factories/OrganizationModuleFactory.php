<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModuleKey;
use App\Models\Organization;
use App\Models\OrganizationModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationModule>
 */
class OrganizationModuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'module_key' => fake()->randomElement(ModuleKey::toggleable()),
            'is_enabled' => false,
            'enabled_at' => null,
            'disabled_at' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => true,
            'enabled_at' => now(),
            'disabled_at' => null,
        ]);
    }
}
