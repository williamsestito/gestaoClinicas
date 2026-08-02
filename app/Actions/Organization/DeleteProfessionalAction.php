<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;

/**
 * Exclusão lógica — nunca física. Nunca apaga o `User` vinculado, o
 * `SiteProfessional`, memberships ou a foto — apenas marca o cadastro
 * operacional como excluído, preservando todo o histórico.
 */
class DeleteProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional): void
    {
        $professional->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $professional,
            before: ['status' => $professional->status->value],
            organization: $professional->organization,
        );
    }
}
