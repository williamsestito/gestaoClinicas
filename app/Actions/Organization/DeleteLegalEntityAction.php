<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\LegalEntity;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica (SoftDeletes) de uma entidade legal — nunca exclusão
 * física. A entidade principal não pode ser excluída sem antes designar
 * outra como principal (ver SetPrimaryLegalEntityAction).
 */
class DeleteLegalEntityAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(LegalEntity $legalEntity): void
    {
        if ($legalEntity->is_primary) {
            throw ValidationException::withMessages([
                'legal_entity' => 'Não é possível excluir a entidade legal principal. Defina outra entidade como principal antes de continuar.',
            ]);
        }

        $legalEntity->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $legalEntity,
            before: ['status' => $legalEntity->status->value],
            organization: $legalEntity->organization,
        );
    }
}
