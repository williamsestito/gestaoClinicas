<?php

declare(strict_types=1);

namespace App\Support\Patients;

use App\Enums\AuditAction;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;

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
 * **Deliberadamente nunca casa por telefone/e-mail sozinhos** — diferente
 * de `matchExistingPatient()` (que aceita esse nível de confiança na ponta
 * anônima), aqui o vínculo é automático e silencioso, sem revisão humana no
 * meio, então usa só o identificador mais forte. Achado em uso real: dois
 * telefones/e-mails reaproveitados por pessoas diferentes já teriam
 * misturado o histórico de três leads não relacionados sob um único
 * paciente se o matching aceitasse telefone/e-mail aqui.
 *
 * Chamada tanto pelo cadastro administrativo (CreatePatientAction) quanto
 * pelo autocadastro do portal (RegisterPatientUserAction/AddDependentPatientAction).
 */
final class OrphanAppointmentRequestLinker
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function link(Patient $patient): void
    {
        if ($patient->document === null) {
            return;
        }

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
    }
}
