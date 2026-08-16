<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\SharedResource;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre com status inativo — reativação é uma decisão explícita
 * separada (App\Actions\Organization\ActivateResourceAction).
 */
class RestoreResourceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SharedResource $resource): SharedResource
    {
        $conflict = SharedResource::query()
            ->where('organization_id', $resource->organization_id)
            ->where('unit_id', $resource->unit_id)
            ->where('id', '!=', $resource->id)
            ->where('name', $resource->name)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'resource' => 'Não foi possível restaurar porque já existe um recurso ativo com o mesmo nome nesta unidade.',
            ]);
        }

        $resource->restore();
        $resource->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $resource,
            after: ['status' => $resource->status->value],
            organization: $resource->organization,
            unit: $resource->unit,
        );

        return $resource;
    }
}
