<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalSpecialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre inativo e nunca como principal — reativação e definição
 * de principal são decisões explícitas separadas.
 */
class RestoreProfessionalSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalSpecialty $link): ProfessionalSpecialty
    {
        $conflict = ProfessionalSpecialty::query()
            ->where('professional_id', $link->professional_id)
            ->where('specialty_id', $link->specialty_id)
            ->where('id', '!=', $link->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'specialty' => 'Já existe um vínculo ativo com este registro.',
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
