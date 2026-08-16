<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre com status inativo — reativação é uma decisão explícita
 * separada (App\Actions\Organization\ActivatePatientAction). Antes de
 * restaurar, revalida se um novo registro ativo com o mesmo documento já
 * foi criado nesse meio-tempo.
 */
class RestorePatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Patient $patient): Patient
    {
        if ($patient->document !== null) {
            $conflict = Patient::query()
                ->where('organization_id', $patient->organization_id)
                ->where('id', '!=', $patient->id)
                ->where('document', $patient->document)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'patient' => 'Não foi possível restaurar porque já existe um paciente ativo com o mesmo documento.',
                ]);
            }
        }

        $patient->restore();
        $patient->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $patient,
            after: ['status' => $patient->status->value],
            organization: $patient->organization,
        );

        return $patient;
    }
}
