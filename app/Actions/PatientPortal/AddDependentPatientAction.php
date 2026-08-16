<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Enums\AuditAction;
use App\Enums\PatientUserLinkRole;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Support\Auditing\AuditLogger;
use App\Support\Patients\MinorGuardianGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adiciona um dependente a uma conta de portal — usada tanto pelo
 * autocadastro (App\Actions\PatientPortal\RegisterPatientUserAction, quando
 * "registering_for" é "dependent") quanto pela tela autenticada de
 * "adicionar dependente". Diferente de App\Actions\Organization\CreatePatientAction
 * (fluxo administrativo, que exige os contatos/responsáveis no payload):
 * aqui o contato de emergência (RN-003) e, se o dependente for menor, o
 * responsável legal (RN-004) são criados automaticamente a partir dos
 * dados de quem está cadastrando — ver docs/modules/patient-portal.md.
 */
class AddDependentPatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes  name, birth_date, document, phone, relationship, responsible_phone
     */
    public function handle(PatientUser $patientUser, Organization $organization, array $attributes): Patient
    {
        $isMinor = MinorGuardianGuard::isMinor((string) $attributes['birth_date']);

        return DB::transaction(function () use ($patientUser, $organization, $attributes, $isMinor) {
            try {
                $patient = $organization->patients()->create([
                    'name' => $attributes['name'],
                    'birth_date' => $attributes['birth_date'],
                    'document' => $attributes['document'] ?? null,
                    'phone' => $attributes['phone'] ?? null,
                    'origin' => 'patient-portal',
                    'status' => RecordStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Mensagem deliberadamente genérica — confirmar que o CPF já
                // pertence a outro cadastro exporia dado de um terceiro sem
                // vínculo com esta conta (achado de security-review).
                throw ValidationException::withMessages([
                    'document' => 'Não foi possível concluir o cadastro com este documento. Confirme os dados ou entre em contato com a clínica.',
                ]);
            }

            $patient->portalLink()->create([
                'organization_id' => $organization->id,
                'patient_user_id' => $patientUser->id,
                'role' => PatientUserLinkRole::Dependent,
            ]);

            $patient->emergencyContacts()->create([
                'organization_id' => $organization->id,
                'name' => $patientUser->name,
                'relationship' => $attributes['relationship'],
                'phone_primary' => $attributes['responsible_phone'],
            ]);

            if ($isMinor) {
                $patient->responsibles()->create([
                    'organization_id' => $organization->id,
                    'name' => $patientUser->name,
                    'phone' => $attributes['responsible_phone'],
                    'relationship' => $attributes['relationship'],
                    'is_legal_guardian' => true,
                ]);
            }

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $patient,
                after: $patient->only(['name', 'birth_date', 'status']),
                organization: $organization,
            );

            $this->auditLogger->log(
                AuditAction::Linked,
                auditable: $patient,
                after: ['patient_user_id' => $patientUser->id, 'role' => PatientUserLinkRole::Dependent->value],
                organization: $organization,
            );

            return $patient;
        });
    }
}
