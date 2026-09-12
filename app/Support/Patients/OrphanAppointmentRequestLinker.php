<?php

declare(strict_types=1);

namespace App\Support\Patients;

use App\Enums\AuditAction;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vincula retroativamente pré-agendamentos (leads) "órfãos" — `patient_id`
 * nulo — a um `Patient` recém-criado, quando o documento (CPF) bate
 * exatamente. Espelha, na direção contrária,
 * App\Actions\Public\CreateAppointmentRequestAction::matchExistingPatient()
 * (que só casa um lead novo contra um Patient já existente, nunca cria
 * um). Sem isso, um lead enviado antes do cadastro/autocadastro do
 * paciente nunca aparecia no portal nem em "Meus pacientes"/"Meus
 * pré-agendamentos" do profissional, mesmo depois do mesmo documento
 * virar um Patient de verdade — achado reproduzido em uso real (paciente
 * "testeuchoa": lead às 21:27:17, Patient/PatientUser às 21:28:02,
 * `appointment_requests.patient_id` permanecia nulo).
 *
 * **Só vincula automaticamente por CPF exato** — diferente de
 * `matchExistingPatient()` (que aceita telefone/e-mail na ponta anônima),
 * aqui o vínculo é automático e silencioso, sem revisão humana no meio,
 * então usa só o identificador mais forte. Achado em uso real: dois
 * telefones/e-mails reaproveitados por pessoas diferentes já teriam
 * misturado o histórico de três leads não relacionados sob um único
 * paciente se o matching aceitasse telefone/e-mail aqui.
 *
 * Quando não há CPF batendo (paciente sem documento — só possível hoje via
 * cadastro administrativo, já que o autocadastro do portal passou a exigir
 * CPF — ou CPF que não bate com nenhum lead), tenta telefone/whatsapp/
 * e-mail só como indício e **nunca vincula sozinho**: grava um
 * `AuditAction::FlaggedForReview` por lead candidato, para a recepção
 * revisar e vincular manualmente (RN de matching assistido).
 *
 * Chamada tanto pelo cadastro administrativo (CreatePatientAction) quanto
 * pelo autocadastro do portal (RegisterPatientUserAction/AddDependentPatientAction).
 */
final class OrphanAppointmentRequestLinker
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function link(Patient $patient): void
    {
        if ($patient->document !== null) {
            $matches = AppointmentRequest::query()
                ->where('organization_id', $patient->organization_id)
                ->whereNull('patient_id')
                ->where('document', $patient->document)
                ->get();

            foreach ($matches as $request) {
                $before = $request->only('patient_id');
                $request->update(['patient_id' => $patient->id]);

                $this->auditLogger->log(
                    AuditAction::Linked,
                    auditable: $request,
                    before: $before,
                    after: ['patient_id' => $patient->id],
                    organization: $patient->organization,
                );
            }

            if ($matches->isNotEmpty()) {
                return;
            }
        }

        $this->flagAmbiguousCandidates($patient);
    }

    /**
     * Nunca atribui `patient_id` — só audita a ambiguidade, para revisão
     * manual da recepção/administração (nunca mescla/vincula pacientes em
     * potencial automaticamente sem revisão humana).
     */
    private function flagAmbiguousCandidates(Patient $patient): void
    {
        $hasAnyCriteria = false;

        $candidates = AppointmentRequest::query()
            ->where('organization_id', $patient->organization_id)
            ->whereNull('patient_id')
            ->where(function (Builder $query) use ($patient, &$hasAnyCriteria) {
                if ($patient->phone !== null) {
                    $query->orWhere('phone', $patient->phone);
                    $hasAnyCriteria = true;
                }

                if ($patient->whatsapp !== null) {
                    $query->orWhere('phone', $patient->whatsapp);
                    $hasAnyCriteria = true;
                }

                if ($patient->email !== null) {
                    $query->orWhere('email', $patient->email);
                    $hasAnyCriteria = true;
                }
            })
            ->get();

        if (! $hasAnyCriteria) {
            return;
        }

        foreach ($candidates as $request) {
            $this->auditLogger->log(
                AuditAction::FlaggedForReview,
                auditable: $request,
                after: ['possible_patient_id' => $patient->id],
                organization: $patient->organization,
            );
        }
    }
}
