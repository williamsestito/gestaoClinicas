<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class ChangeLegalEntityStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(LegalEntity $legalEntity, RecordStatus $status): LegalEntity
    {
        if ($status === RecordStatus::Inactive && $legalEntity->is_primary) {
            throw ValidationException::withMessages([
                'legal_entity' => 'Não é possível inativar a entidade legal principal. Defina outra entidade como principal antes de continuar.',
            ]);
        }

        $previousStatus = $legalEntity->status;

        $legalEntity->update(['status' => $status]);

        $this->auditLogger->log(
            $status === RecordStatus::Active ? AuditAction::Activated : AuditAction::Deactivated,
            auditable: $legalEntity,
            before: ['status' => $previousStatus->value],
            after: ['status' => $status->value],
            organization: $legalEntity->organization,
        );

        return $legalEntity;
    }
}
