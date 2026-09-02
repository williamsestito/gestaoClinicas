<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalService;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateProfessionalServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalService $link): ProfessionalService
    {
        if ($link->service->status !== RecordStatus::Active || $link->service->trashed()) {
            throw ValidationException::withMessages([
                'service' => 'Não é possível ativar o vínculo porque o serviço não está mais ativo.',
            ]);
        }

        if ($link->hasNoCompatibleUnits()) {
            throw ValidationException::withMessages([
                'service' => 'Não é possível ativar o vínculo porque não há unidade compatível entre o profissional e o serviço.',
            ]);
        }

        $previousStatus = $link->status;

        $link->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $link,
            before: ['status' => $previousStatus->value],
            after: ['status' => $link->status->value],
            organization: $link->organization,
        );

        return $link;
    }
}
