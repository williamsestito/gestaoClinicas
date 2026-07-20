<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PermissionKey;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $key = fake()->unique()->randomElement(PermissionKey::cases());

        return [
            'key' => $key->value,
            'group' => $key->group(),
            'label' => $key->label(),
            'description' => null,
        ];
    }
}
