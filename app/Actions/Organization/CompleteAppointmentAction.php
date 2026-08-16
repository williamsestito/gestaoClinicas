<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class CompleteAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::InProgress) {
            throw ValidationException::withMessages([
                'appointment' => 'Só é possível concluir um atendimento em andamento.',
            ]);
        }

        $appointment->update([
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $appointment,
            before: ['status' => AppointmentStatus::InProgress->value],
            after: ['status' => AppointmentStatus::Completed->value],
            organization: $appointment->organization,
            unit: $appointment->unit,
        );

        return $appointment;
    }
}
