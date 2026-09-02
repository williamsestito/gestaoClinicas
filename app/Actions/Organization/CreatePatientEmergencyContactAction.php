<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use App\Support\Auditing\AuditLogger;

class CreatePatientEmergencyContactAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Patient $patient, array $attributes): PatientEmergencyContact
    {
        $contact = $patient->emergencyContacts()->create([
            ...$attributes,
            'organization_id' => $patient->organization_id,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $contact,
            after: $contact->only(['name', 'relationship']),
            organization: $patient->organization,
        );

        return $contact;
    }
}
