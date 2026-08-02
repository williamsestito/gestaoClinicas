<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalSpecialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica do vínculo — nunca exclui a especialidade nem o
 * profissional.
 */
class RemoveProfessionalSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalSpecialty $link): void
    {
        if ($link->is_primary && $this->hasOtherActiveLinks($link)) {
            throw ValidationException::withMessages([
                'specialty' => 'Não é possível remover a especialidade principal sem definir uma substituta.',
            ]);
        }

        $link->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $link,
            before: ['status' => $link->status->value, 'is_primary' => $link->is_primary],
            organization: $link->organization,
        );
    }

    private function hasOtherActiveLinks(ProfessionalSpecialty $link): bool
    {
        return $link->professional->specialtyLinks()
            ->where('id', '!=', $link->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }
}
