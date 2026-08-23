<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AuditAction;
use App\Models\AppointmentRequest;
use App\Models\PatientUser;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Paciente cancela o próprio pré-agendamento (lead ainda aguardando contato/
 * contatado, nunca um já convertido em Appointment — o controller nunca
 * resolve um `appointment_id` preenchido para esta ação). Mesma convenção de
 * autoria de App\Actions\PatientPortal\CancelPatientAppointmentAction:
 * `patient_user_id`/`cancelled_by: 'patient_portal'` no `after` do audit log,
 * já que não há coluna dedicada para autoria de PatientUser em
 * AppointmentRequest.
 */
class CancelPatientAppointmentRequestAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(AppointmentRequest $appointmentRequest, PatientUser $patientUser): AppointmentRequest
    {
        if ($appointmentRequest->status === AppointmentRequestStatus::Cancelled) {
            throw ValidationException::withMessages([
                'appointmentRequest' => 'Esta solicitação já está cancelada.',
            ]);
        }

        $before = ['status' => $appointmentRequest->status->value];

        $appointmentRequest->update(['status' => AppointmentRequestStatus::Cancelled]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointmentRequest,
            before: $before,
            after: [
                'status' => AppointmentRequestStatus::Cancelled->value,
                'cancelled_by' => 'patient_portal',
                'patient_user_id' => $patientUser->id,
            ],
            organization: $appointmentRequest->organization,
        );

        return $appointmentRequest;
    }
}
