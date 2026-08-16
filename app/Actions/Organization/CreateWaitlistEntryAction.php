<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\WaitlistEntryStatus;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use App\Support\Auditing\AuditLogger;

class CreateWaitlistEntryAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Organization $organization, Unit $unit, array $attributes): WaitlistEntry
    {
        $entry = WaitlistEntry::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'professional_id' => $attributes['professional_id'] ?? null,
            'service_id' => $attributes['service_id'],
            'patient_id' => $attributes['patient_id'],
            'preferred_date' => $attributes['preferred_date'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'status' => WaitlistEntryStatus::Waiting,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $entry,
            after: $entry->only(['unit_id', 'professional_id', 'service_id', 'patient_id', 'status']),
            organization: $organization,
            unit: $unit,
        );

        return $entry;
    }
}
