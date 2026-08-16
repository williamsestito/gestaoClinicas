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
 * Cancelamento pelo próprio paciente/dependente (Etapa 3.3 do roadmap) —
 * mesma regra de App\Actions\Organization\CancelAppointmentAction (bloqueia
 * estado final), mas não a reaproveita diretamente: não há onde registrar
 * a autoria de um App\Models\PatientUser nela. Mesma convenção de
 * App\Actions\PatientPortal\BookAppointmentAction — autoria no `after` do
 * audit log, não no schema.
 */
class CancelPatientAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment, PatientUser $patientUser, string $reason): Appointment
    {
        if ($appointment->status->isFinal()) {
            throw ValidationException::withMessages([
                'appointment' => 'Este agendamento já está em um estado final.',
            ]);
        }

        $previousStatus = $appointment->status;

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => $previousStatus->value],
            after: [
                'status' => $appointment->status->value,
                'cancellation_reason' => $reason,
                'cancelled_by' => 'patient_portal',
                'patient_user_id' => $patientUser->id,
            ],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
