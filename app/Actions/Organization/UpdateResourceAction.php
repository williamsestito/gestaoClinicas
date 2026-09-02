<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\SharedResource;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class UpdateResourceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(SharedResource $resource, array $attributes): SharedResource
    {
        $allowed = [
            'unit_id' => $attributes['unit_id'],
            'name' => $attributes['name'],
            'type' => $attributes['type'] ?? null,
        ];

        $before = $resource->only(array_keys($allowed));

        try {
            $resource->update($allowed);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Já existe um recurso com este nome nesta unidade.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $resource,
            before: $before,
            after: $resource->only(array_keys($allowed)),
            organization: $resource->organization,
            unit: $resource->unit,
        );

        return $resource;
    }
}
