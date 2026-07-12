<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitMembership>
 */
class UnitMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_membership_id' => OrganizationMembership::factory(),
            'unit_id' => Unit::factory(),
            'status' => RecordStatus::Active,
            'is_manager' => false,
        ];
    }
}
