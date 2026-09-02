<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Service;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre com status inativo — reativação é uma decisão explícita
 * separada (ActivateServiceAction). Não recria vínculos com especialidades
 * ou unidades removidos antes da exclusão: eles precisam ser refeitos
 * explicitamente na edição, já que podem ter deixado de fazer sentido.
 */
class RestoreServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Service $service): Service
    {
        $conflict = Service::query()
            ->where('organization_id', $service->organization_id)
            ->where('id', '!=', $service->id)
            ->where('code', $service->code)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'service' => 'Não foi possível restaurar porque já existe um registro ativo com os mesmos dados.',
            ]);
        }

        $service->restore();
        $service->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $service,
            after: ['status' => $service->status->value],
            organization: $service->organization,
        );

        return $service;
    }
}
