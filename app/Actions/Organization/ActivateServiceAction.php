<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Enums\RecordStatus;
use App\Models\Service;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Service $service): Service
    {
        if ($service->organization->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'service' => 'Não é possível ativar um serviço de uma clínica que não está ativa.',
            ]);
        }

        $previousStatus = $service->status;

        $service->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $service,
            before: ['status' => $previousStatus->value],
            after: ['status' => $service->status->value],
            organization: $service->organization,
        );

        return $service;
    }
}
