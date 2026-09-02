<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Data\Organization\AddressData;
use App\Enums\AuditAction;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;

/**
 * Equivalente de App\Actions\Organization\UpdatePatientAction, mas restrito
 * aos campos que o próprio paciente/responsável pode editar pelo portal —
 * nunca preferred_unit_id/primary_professional_id/status, que são
 * exclusivamente administrativos.
 */
class UpdatePatientPortalProfileAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Patient $patient, array $attributes, ?AddressData $address): Patient
    {
        $allowed = collect($attributes)->only([
            'name', 'preferred_name', 'document', 'phone', 'whatsapp', 'email',
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
