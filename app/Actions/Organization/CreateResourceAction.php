<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\SharedResource;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateResourceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Organization $organization, Unit $unit, array $attributes): SharedResource
    {
        try {
            $resource = $organization->resources()->create([
                'unit_id' => $unit->id,
                'name' => $attributes['name'],
                'type' => $attributes['type'] ?? null,
                'status' => RecordStatus::Active,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Já existe um recurso com este nome nesta unidade.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $resource,
            after: $resource->only(['unit_id', 'name', 'type', 'status']),
            organization: $organization,
            unit: $unit,
        );

        return $resource;
    }
}
