<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalWorkingHour;
use App\Support\Auditing\AuditLogger;

/**
 * Exclusão lógica do intervalo — nunca exclui o vínculo profissional-
 * unidade, o profissional nem a unidade. Não há agenda para revalidar
 * nesta fase.
 */
class DeleteProfessionalWorkingHourAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalWorkingHour $workingHour): void
    {
        $organization = $workingHour->organization;

        $workingHour->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $workingHour,
            before: ['status' => $workingHour->status->value],
            organization: $organization,
        );
    }
}
