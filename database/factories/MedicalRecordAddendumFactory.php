<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordAddendum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecordAddendum>
 */
class MedicalRecordAddendumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'medical_record_id' => MedicalRecord::factory()->finalized(),
            'organization_id' => fn (array $attributes) => MedicalRecord::query()->findOrFail((string) $attributes['medical_record_id'])->organization_id,
            'unit_id' => fn (array $attributes) => MedicalRecord::query()->findOrFail((string) $attributes['medical_record_id'])->unit_id,
            'professional_id' => fn (array $attributes) => MedicalRecord::query()->findOrFail((string) $attributes['medical_record_id'])->professional_id,
            'body' => fake()->sentence(),
        ];
    }
}
