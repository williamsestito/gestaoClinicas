<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica do vínculo — nunca exclui a unidade nem o profissional.
 */
class RemoveProfessionalFromUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalUnit $link): void
    {
        if ($link->is_primary && $this->hasOtherActiveLinks($link)) {
            throw ValidationException::withMessages([
                'unit' => 'Não é possível remover a unidade principal sem definir uma substituta.',
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

    private function hasOtherActiveLinks(ProfessionalUnit $link): bool
    {
        return $link->professional->unitLinks()
            ->where('id', '!=', $link->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }
}
