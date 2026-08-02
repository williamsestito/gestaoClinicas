<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalTimeBlock;
use App\Support\Auditing\AuditLogger;

/**
 * Exclusão lógica do bloqueio — nunca exclui o profissional nem a unidade.
 */
class DeleteProfessionalTimeBlockAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalTimeBlock $timeBlock): void
    {
        $organization = $timeBlock->organization;

        $timeBlock->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $timeBlock,
            before: ['status' => $timeBlock->status->value],
            organization: $organization,
        );
    }
}
