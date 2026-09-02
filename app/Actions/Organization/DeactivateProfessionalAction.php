<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;

/**
 * Inativar um profissional nunca inativa o usuário vinculado, nem remove
 * suas permissões (que dependem exclusivamente de OrganizationMembership/
 * Role) — apenas prepara o cadastro para deixar de ser usado em operações
 * futuras (ex.: agenda).
 */
class DeactivateProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional): Professional
    {
        $previousStatus = $professional->status;

        $professional->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $professional,
            before: ['status' => $previousStatus->value],
            after: ['status' => $professional->status->value],
            organization: $professional->organization,
        );

        return $professional;
    }
}
