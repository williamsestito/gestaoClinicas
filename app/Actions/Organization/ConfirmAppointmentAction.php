<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Confirma um agendamento criado pelo paciente pelo portal (Etapa 3.2), que
 * entra em `Requested` — nunca alcançável por criação feita pelo staff, que
 * já entra direto em `Confirmed` (ver App\Actions\Organization\CreateAppointmentAction).
 */
class ConfirmAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Requested) {
            throw ValidationException::withMessages([
                'appointment' => 'Apenas agendamentos com status "Solicitado" podem ser confirmados.',
            ]);
        }

        $appointment->update(['status' => AppointmentStatus::Confirmed]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => AppointmentStatus::Requested->value],
            after: ['status' => AppointmentStatus::Confirmed->value],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
