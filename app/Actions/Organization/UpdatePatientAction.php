<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\AddressData;
use App\Enums\AuditAction;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;

class UpdatePatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Patient $patient, array $attributes, ?AddressData $address): Patient
    {
        $allowed = collect($attributes)->only([
            'name', 'preferred_name', 'document', 'birth_date', 'phone', 'whatsapp',
            'email', 'origin', 'preferred_unit_id', 'primary_professional_id',
        ])->all();

        $before = $patient->only(array_keys($allowed));

        $patient->update($allowed);

        if ($address !== null) {
            $patient->address()->updateOrCreate([], [
                ...$address->toArray(),
                'organization_id' => $patient->organization_id,
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $patient,
            before: $before,
            after: $patient->only(array_keys($allowed)),
            organization: $patient->organization,
        );

        return $patient;
    }
}
