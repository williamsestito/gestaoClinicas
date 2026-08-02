<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateProfessionalUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalUnit $link): ProfessionalUnit
    {
        if ($link->unit->status !== RecordStatus::Active || $link->unit->trashed()) {
            throw ValidationException::withMessages([
                'unit' => 'Não é possível ativar o vínculo porque a unidade não está mais ativa.',
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
