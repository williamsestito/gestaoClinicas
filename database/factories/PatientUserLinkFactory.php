<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PatientUserLinkRole;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientUserLink>
 */
class PatientUserLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'patient_user_id' => fn (array $attributes) => PatientUser::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'patient_id' => fn (array $attributes) => Patient::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'role' => PatientUserLinkRole::Self,
        ];
    }

    public function dependent(): static
    {
        return $this->state(fn (array $attributes) => ['role' => PatientUserLinkRole::Dependent]);
    }
}
