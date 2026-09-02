<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use App\Models\ProfessionalService;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre inativo e sem unidades selecionadas — o profissional e o
 * serviço podem ter mudado desde a exclusão, então a seleção de unidades
 * precisa ser refeita explicitamente após a restauração.
 */
class RestoreProfessionalServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalService $link): ProfessionalService
    {
        $conflict = ProfessionalService::query()
            ->where('professional_id', $link->professional_id)
            ->where('service_id', $link->service_id)
            ->where('id', '!=', $link->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'service' => 'Já existe um vínculo ativo com este serviço.',
            ]);
        }

        $link->restore();
        $link->update(['status' => RecordStatus::Inactive, 'unit_scope' => ProfessionalServiceUnitScope::None]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $link,
            after: ['status' => $link->status->value],
            organization: $link->organization,
        );

        return $link;
    }
}
