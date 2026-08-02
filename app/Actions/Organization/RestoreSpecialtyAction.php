<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Specialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre com status inativo — reativação é uma decisão explícita
 * separada (App\Actions\Organization\ActivateSpecialtyAction), nunca
 * automática. Antes de restaurar, revalida se um novo registro ativo com o
 * mesmo nome/código já foi criado nesse meio-tempo.
 */
class RestoreSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Specialty $specialty): Specialty
    {
        $conflict = Specialty::query()
            ->where('organization_id', $specialty->organization_id)
            ->where('id', '!=', $specialty->id)
            ->where(fn ($query) => $query->where('name', $specialty->name)->orWhere('code', $specialty->code))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'specialty' => 'Não foi possível restaurar porque já existe um registro ativo com os mesmos dados.',
            ]);
        }

        $specialty->restore();
        $specialty->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $specialty,
            after: ['status' => $specialty->status->value],
            organization: $specialty->organization,
        );

        return $specialty;
    }
}
