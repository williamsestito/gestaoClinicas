<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalSpecialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class DeactivateProfessionalSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalSpecialty $link): ProfessionalSpecialty
    {
        if ($link->is_primary && $this->hasOtherActiveLinks($link)) {
            throw ValidationException::withMessages([
                'specialty' => 'Não é possível inativar a especialidade principal sem definir uma substituta.',
            ]);
        }

        $previousStatus = $link->status;

        $link->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $link,
            before: ['status' => $previousStatus->value],
            after: ['status' => $link->status->value],
            organization: $link->organization,
        );

        return $link;
    }

    private function hasOtherActiveLinks(ProfessionalSpecialty $link): bool
    {
        return $link->professional->specialtyLinks()
            ->where('id', '!=', $link->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }
}
