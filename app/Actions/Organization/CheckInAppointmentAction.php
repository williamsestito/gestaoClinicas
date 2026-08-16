<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class CheckInAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Confirmed) {
            throw ValidationException::withMessages([
                'appointment' => 'Só é possível fazer check-in de um agendamento confirmado.',
            ]);
        }

        $appointment->update([
            'status' => AppointmentStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => AppointmentStatus::Confirmed->value],
            after: ['status' => AppointmentStatus::CheckedIn->value],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
