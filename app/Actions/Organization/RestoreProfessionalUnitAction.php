<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre inativo e nunca como principal — reativação e definição
 * de principal são decisões explícitas separadas.
 */
class RestoreProfessionalUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalUnit $link): ProfessionalUnit
    {
        $conflict = ProfessionalUnit::query()
            ->where('professional_id', $link->professional_id)
            ->where('unit_id', $link->unit_id)
            ->where('id', '!=', $link->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'unit' => 'Já existe um vínculo ativo com esta unidade.',
            ]);
        }

        $link->restore();
        $link->update(['status' => RecordStatus::Inactive, 'is_primary' => false]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $link,
            after: ['status' => $link->status->value],
            organization: $link->organization,
        );

        return $link;
    }
}
