<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Support\Auditing\AuditLogger;

class UpdateOrganizationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Organization $organization, array $attributes): Organization
    {
        $allowed = collect($attributes)->only([
            'name', 'default_timezone', 'default_currency', 'locale', 'primary_color', 'secondary_color',
        ])->all();

        $before = $organization->only(array_keys($allowed));

        $organization->update($allowed);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $organization,
            before: $before,
            after: $organization->only(array_keys($allowed)),
            organization: $organization,
        );

        return $organization;
    }
}
