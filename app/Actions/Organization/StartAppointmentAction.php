<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class StartAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::CheckedIn) {
            throw ValidationException::withMessages([
                'appointment' => 'Só é possível iniciar o atendimento após o check-in.',
            ]);
        }

        $appointment->update([
            'status' => AppointmentStatus::InProgress,
            'started_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => AppointmentStatus::CheckedIn->value],
            after: ['status' => AppointmentStatus::InProgress->value],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
