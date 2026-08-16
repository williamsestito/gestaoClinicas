<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\PatientEmergencyContact;
use App\Support\Auditing\AuditLogger;

class UpdatePatientEmergencyContactAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PatientEmergencyContact $contact, array $attributes): PatientEmergencyContact
    {
        $before = $contact->only(['name', 'relationship', 'phone_primary', 'phone_secondary']);

        $contact->update($attributes);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $contact,
            before: $before,
            after: $contact->only(array_keys($before)),
            organization: $contact->organization,
        );

        return $contact;
    }
}
