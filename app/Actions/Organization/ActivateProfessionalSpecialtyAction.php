<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalSpecialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateProfessionalSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalSpecialty $link): ProfessionalSpecialty
    {
        if ($link->specialty->status !== RecordStatus::Active || $link->specialty->trashed()) {
            throw ValidationException::withMessages([
                'specialty' => 'Não é possível ativar o vínculo porque a especialidade não está mais ativa.',
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
