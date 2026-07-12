<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\Organization;
use App\Support\Documents\BrazilianState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'postal_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'complement' => null,
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(BrazilianState::codes()),
            'country' => 'BR',
        ];
    }
}
