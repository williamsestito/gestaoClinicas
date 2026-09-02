<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\PatientResponsible;
use App\Support\Auditing\AuditLogger;
use App\Support\Patients\MinorGuardianGuard;
use Illuminate\Validation\ValidationException;

class UpdatePatientResponsibleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PatientResponsible $responsible, array $attributes): PatientResponsible
    {
        $losingLegalGuardianRole = $responsible->is_legal_guardian && ! ($attributes['is_legal_guardian'] ?? false);

        if ($losingLegalGuardianRole && $responsible->patient->isMinor()
            && ! MinorGuardianGuard::hasOtherActiveLegalGuardian($responsible->patient, $responsible->id)) {
            throw ValidationException::withMessages([
                'is_legal_guardian' => 'Não é possível remover o único responsável legal de um paciente menor de 18 anos.',
            ]);
        }

        $before = $responsible->only(['name', 'phone', 'relationship', 'is_legal_guardian', 'is_financial_responsible', 'is_authorized_representative']);

        $responsible->update($attributes);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $responsible,
            before: $before,
            after: $responsible->only(array_keys($before)),
            organization: $responsible->organization,
        );

        return $responsible;
    }
}
