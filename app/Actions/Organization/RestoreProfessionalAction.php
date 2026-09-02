<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre com status inativo — reativação é uma decisão explícita
 * separada (ActivateProfessionalAction). Revalida conflito de documento
 * antes de restaurar; não recria o vínculo com usuário automaticamente
 * (ele já está preservado na própria coluna, mas o usuário pode ter deixado
 * de ser elegível nesse meio-tempo — por isso ele é revalidado aqui e
 * removido silenciosamente se não estiver mais elegível).
 */
class RestoreProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional): Professional
    {
        if ($professional->document !== null) {
            $conflict = Professional::query()
                ->where('organization_id', $professional->organization_id)
                ->where('id', '!=', $professional->id)
                ->where('document', $professional->document)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'professional' => 'Não foi possível restaurar porque já existe um registro ativo com os mesmos dados.',
                ]);
            }
        }

        $professional->restore();
        $professional->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $professional,
            after: ['status' => $professional->status->value],
            organization: $professional->organization,
        );

        return $professional;
    }
}
