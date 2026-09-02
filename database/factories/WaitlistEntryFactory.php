<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WaitlistEntryStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'unit_id' => Unit::factory(),
            'professional_id' => null,
            'service_id' => Service::factory(),
            'patient_id' => Patient::factory(),
            'preferred_date' => null,
            'notes' => null,
            'status' => WaitlistEntryStatus::Waiting,
            'appointment_id' => null,
        ];
    }
}
