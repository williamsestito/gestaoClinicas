<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;

class UpdateProfessionalUnitAssignmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ProfessionalUnit $link, array $attributes): ProfessionalUnit
    {
        $before = $link->only(['starts_on', 'ends_on']);

        $link->update([
            'starts_on' => $attributes['starts_on'] ?? null,
            'ends_on' => $attributes['ends_on'] ?? null,
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $link,
            before: $before,
            after: $link->only(['starts_on', 'ends_on']),
            organization: $link->organization,
        );

        return $link;
    }
}
