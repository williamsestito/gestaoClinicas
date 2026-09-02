<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DashboardReminderColor;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalDashboardReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalDashboardReminder>
 */
class ProfessionalDashboardReminderFactory extends Factory
{
    protected $model = ProfessionalDashboardReminder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'professional_id' => Professional::factory(),
            'body' => fake()->sentence(),
            'color' => fake()->randomElement(DashboardReminderColor::cases()),
        ];
    }
}
