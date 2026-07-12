<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\LegalEntity;
use App\Support\Auditing\AuditLogger;

class UpdateLegalEntityAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(LegalEntity $legalEntity, array $attributes): LegalEntity
    {
        $allowed = collect($attributes)->only([
            'legal_name', 'trade_name', 'state_registration', 'municipal_registration', 'email', 'phone',
        ])->all();

        $before = $legalEntity->only(array_keys($allowed));

        $legalEntity->update($allowed);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $legalEntity,
            before: $before,
            after: $legalEntity->only(array_keys($allowed)),
            organization: $legalEntity->organization,
        );

        return $legalEntity;
    }
}
