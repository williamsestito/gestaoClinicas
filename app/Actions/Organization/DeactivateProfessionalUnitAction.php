<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class DeactivateProfessionalUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalUnit $link): ProfessionalUnit
    {
        if ($link->is_primary && $this->hasOtherActiveLinks($link)) {
            throw ValidationException::withMessages([
                'unit' => 'Não é possível inativar a unidade principal sem definir uma substituta.',
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

    private function hasOtherActiveLinks(ProfessionalUnit $link): bool
    {
        return $link->professional->unitLinks()
            ->where('id', '!=', $link->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }
}
