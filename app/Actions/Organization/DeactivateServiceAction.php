<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Service;
use App\Support\Auditing\AuditLogger;

/**
 * Inativar um serviço nunca destrói os vínculos existentes com
 * profissionais, especialidades ou unidades, nem altera SiteService — ele
 * apenas deixa de poder ser usado em operações futuras (ex.: agenda).
 */
class DeactivateServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Service $service): Service
    {
        $previousStatus = $service->status;

        $service->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $service,
            before: ['status' => $previousStatus->value],
            after: ['status' => $service->status->value],
            organization: $service->organization,
        );

        return $service;
    }
}
