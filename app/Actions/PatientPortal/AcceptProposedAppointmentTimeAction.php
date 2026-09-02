<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\PatientUser;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Paciente aceita o horário alternativo proposto pela recepção (Etapa 3.3
 * do roadmap) — `AwaitingConfirmation` → `Confirmed`. Recusar reaproveita
 * App\Actions\PatientPortal\CancelPatientAppointmentAction sem alteração
 * (motivo fixo "Horário proposto recusado" definido pelo controller).
 */
class AcceptProposedAppointmentTimeAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment, PatientUser $patientUser): Appointment
    {
        if ($appointment->status !== AppointmentStatus::AwaitingConfirmation) {
            throw ValidationException::withMessages([
                'appointment' => 'Este agendamento não está aguardando confirmação de horário.',
            ]);
        }

        $appointment->update(['status' => AppointmentStatus::Confirmed]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => AppointmentStatus::AwaitingConfirmation->value],
            after: [
                'status' => AppointmentStatus::Confirmed->value,
                'accepted_by' => 'patient_portal',
                'patient_user_id' => $patientUser->id,
            ],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
