<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class MarkAppointmentNoShowAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment): Appointment
    {
        if (! in_array($appointment->status, [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn], true)) {
            throw ValidationException::withMessages([
                'appointment' => 'Este agendamento não pode ser marcado como não comparecido neste estado.',
            ]);
        }

        if ($appointment->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'appointment' => 'Não é possível marcar não comparecimento antes do horário do agendamento.',
            ]);
        }

        $previousStatus = $appointment->status;

        $appointment->update(['status' => AppointmentStatus::NoShow]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => $previousStatus->value],
            after: ['status' => $appointment->status->value],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
