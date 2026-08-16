<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\PatientResponsible;
use App\Support\Auditing\AuditLogger;
use App\Support\Patients\MinorGuardianGuard;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica do responsável — nunca exclui o paciente. RN-004: um
 * paciente menor de 18 anos nunca pode ficar sem responsável legal ativo.
 */
class DeletePatientResponsibleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(PatientResponsible $responsible): void
    {
        if ($responsible->is_legal_guardian && $responsible->patient->isMinor()
            && ! MinorGuardianGuard::hasOtherActiveLegalGuardian($responsible->patient, $responsible->id)) {
            throw ValidationException::withMessages([
                'responsible' => 'Não é possível remover o único responsável legal de um paciente menor de 18 anos.',
            ]);
        }

        $responsible->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $responsible,
            before: ['is_legal_guardian' => $responsible->is_legal_guardian],
            organization: $responsible->organization,
        );
    }
}
