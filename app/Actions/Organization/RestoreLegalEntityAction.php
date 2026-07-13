<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\LegalEntity;
use App\Support\Auditing\AuditLogger;

class RestoreLegalEntityAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(LegalEntity $legalEntity): void
    {
        $legalEntity->restore();

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $legalEntity,
            after: ['status' => $legalEntity->status->value],
            organization: $legalEntity->organization,
        );
    }
}
