<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Actions\Organization\UpdatePatientPhotoAction;
use App\Enums\AuditAction;
use App\Enums\PatientUserLinkRole;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\PatientUser;
use App\Support\Auditing\AuditLogger;
use App\Support\Patients\OrphanAppointmentRequestLinker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Autocadastro do portal do paciente — cria a conta e, na mesma transação,
 * o primeiro Patient que ela gerencia: "self" (a própria conta é o
 * paciente, adulto — RN validada no Form Request) ou "dependent" (delega a
 * App\Actions\PatientPortal\AddDependentPatientAction, reaproveitada
 * também pela tela autenticada de "adicionar dependente"). Ao contrário do
 * cadastro administrativo (CreatePatientAction), o paciente "self" não tem
 * contato de emergência obrigatório aqui — exceção estreita e deliberada:
 * o próprio login/telefone do paciente já cumpre esse papel (ver
 * docs/modules/patient-portal.md).
 */
class RegisterPatientUserAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AddDependentPatientAction $addDependentPatientAction,
        private readonly UpdatePatientPhotoAction $updatePatientPhotoAction,
        private readonly OrphanAppointmentRequestLinker $orphanAppointmentRequestLinker,
    ) {}

    /**
     * @param  array<string, mixed>  $accountAttributes  name, email, password
     * @param  array<string, mixed>  $selfAttributes  birth_date, document, phone (registering_for=self)
     * @param  array<string, mixed>  $dependentAttributes  name, birth_date, document, phone, relationship, responsible_phone (registering_for=dependent)
     */
    public function handle(
        Organization $organization,
        array $accountAttributes,
        string $registeringFor,
        array $selfAttributes,
        array $dependentAttributes,
        ?UploadedFile $photo = null,
    ): PatientUser {
        return DB::transaction(function () use ($organization, $accountAttributes, $registeringFor, $selfAttributes, $dependentAttributes, $photo) {
            $patientUser = $organization->patientUsers()->create($accountAttributes);

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $patientUser,
                after: ['name' => $patientUser->name, 'email' => $patientUser->email],
                organization: $organization,
            );

            if ($registeringFor === 'dependent') {
                $this->addDependentPatientAction->handle($patientUser, $organization, $dependentAttributes);

                return $patientUser;
            }

            try {
                $patient = $organization->patients()->create([
                    'name' => $patientUser->name,
                    'email' => $patientUser->email,
                    'birth_date' => $selfAttributes['birth_date'],
                    'document' => $selfAttributes['document'] ?? null,
                    'phone' => $selfAttributes['phone'] ?? null,
                    'origin' => 'patient-portal',
                    'status' => RecordStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Mensagem deliberadamente genérica — confirmar que o CPF já
                // pertence a outro cadastro exporia dado de um terceiro que
                // nunca se autocadastrou (achado de security-review).
                throw ValidationException::withMessages([
                    'document' => 'Não foi possível concluir o cadastro com este documento. Confirme os dados ou entre em contato com a clínica.',
                ]);
            }

            if ($photo !== null) {
                $this->updatePatientPhotoAction->handle($patient, $photo);
            }

            $patient->portalLink()->create([
                'organization_id' => $organization->id,
                'patient_user_id' => $patientUser->id,
                'role' => PatientUserLinkRole::Self,
            ]);

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $patient,
                after: $patient->only(['name', 'birth_date', 'status']),
                organization: $organization,
            );

            $this->auditLogger->log(
                AuditAction::Linked,
                auditable: $patient,
                after: ['patient_user_id' => $patientUser->id, 'role' => PatientUserLinkRole::Self->value],
                organization: $organization,
            );

            $this->orphanAppointmentRequestLinker->link($patient);

            return $patientUser;
        });
    }
}
