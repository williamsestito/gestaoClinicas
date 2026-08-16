<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\SharedResource;
use App\Support\Auditing\AuditLogger;

/**
 * Inativar um recurso nunca desfaz vínculos já existentes com agendamentos
 * passados — apenas impede que ele seja escolhido em novos agendamentos.
 */
class DeactivateResourceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SharedResource $resource): SharedResource
    {
        $previousStatus = $resource->status;

        $resource->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $resource,
            before: ['status' => $previousStatus->value],
            after: ['status' => $resource->status->value],
            organization: $resource->organization,
            unit: $resource->unit,
        );

        return $resource;
    }
}
