<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Enums\RecordStatus;
use App\Models\SharedResource;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateResourceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SharedResource $resource): SharedResource
    {
        if ($resource->organization->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'resource' => 'Não é possível ativar um recurso de uma clínica que não está ativa.',
            ]);
        }

        $previousStatus = $resource->status;

        $resource->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $resource,
            before: ['status' => $previousStatus->value],
            after: ['status' => $resource->status->value],
            organization: $resource->organization,
            unit: $resource->unit,
        );

        return $resource;
    }
}
