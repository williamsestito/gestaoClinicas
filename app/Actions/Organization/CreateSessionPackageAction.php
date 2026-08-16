<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Patient;
use App\Models\SessionPackage;
use App\Support\Auditing\AuditLogger;

class CreateSessionPackageAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Patient $patient, array $attributes): SessionPackage
    {
        $package = $patient->sessionPackages()->create([
            'organization_id' => $patient->organization_id,
            'service_id' => $attributes['service_id'] ?? null,
            'total_sessions' => $attributes['total_sessions'],
            'expires_at' => $attributes['expires_at'] ?? null,
            'status' => RecordStatus::Active,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $package,
            after: $package->only(['service_id', 'total_sessions', 'expires_at', 'status']),
            organization: $patient->organization,
        );

        return $package;
    }
}
