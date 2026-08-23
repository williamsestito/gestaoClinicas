<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MedicalRecordFileCategory;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecordFile>
 */
class MedicalRecordFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'medical_record_id' => MedicalRecord::factory(),
            'organization_id' => fn (array $attributes) => MedicalRecord::query()->findOrFail((string) $attributes['medical_record_id'])->organization_id,
            'unit_id' => fn (array $attributes) => MedicalRecord::query()->findOrFail((string) $attributes['medical_record_id'])->unit_id,
            'uploaded_by' => User::factory(),
            'category' => MedicalRecordFileCategory::Exam,
            'original_filename' => 'exame.pdf',
            'disk' => 'local',
            'path' => 'medical-record-files/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 500000),
        ];
    }
}
