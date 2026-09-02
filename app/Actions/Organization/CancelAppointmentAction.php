<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class CancelAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment, string $reason): Appointment
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
            after: ['status' => $appointment->status->value, 'cancellation_reason' => $reason],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
