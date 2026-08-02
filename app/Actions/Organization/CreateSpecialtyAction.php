<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Specialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Organization $organization, array $attributes): Specialty
    {
        try {
            $specialty = $organization->specialties()->create([
                'name' => $attributes['name'],
                'code' => $attributes['code'] ?? null,
                'description' => $attributes['description'] ?? null,
                'display_order' => $attributes['display_order'] ?? 0,
                'status' => RecordStatus::Active,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma especialidade com este nome ou código nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $specialty,
            after: $specialty->only(['name', 'code', 'status']),
            organization: $organization,
        );

        return $specialty;
    }
}
