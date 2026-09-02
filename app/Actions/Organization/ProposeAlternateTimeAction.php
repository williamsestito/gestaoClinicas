<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\AppointmentOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff propõe outro horário para um agendamento `Requested` (Etapa 3.3 do
 * roadmap) — primeiro uso real de `AppointmentStatus::AwaitingConfirmation`,
 * reservado desde a Etapa 3.1. O paciente aceita
 * (App\Actions\PatientPortal\AcceptProposedAppointmentTimeAction) ou recusa
 * (reaproveita App\Actions\PatientPortal\CancelPatientAppointmentAction).
 */
class ProposeAlternateTimeAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment, CarbonInterface $startsAt, CarbonInterface $endsAt): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Requested) {
            throw ValidationException::withMessages([
                'appointment' => 'Só é possível propor outro horário para um agendamento com status "Solicitado".',
            ]);
        }

        return DB::transaction(function () use ($appointment, $startsAt, $endsAt) {
            AppointmentOverlapGuard::assertWithinAvailability($appointment->professional, $appointment->unit, $startsAt, $endsAt);
            AppointmentOverlapGuard::assertNoConflict($appointment->professional, $startsAt, $endsAt, excludingId: $appointment->id);

            $before = [
                'status' => $appointment->status->value,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
            ];

            $appointment->update([
                'status' => AppointmentStatus::AwaitingConfirmation,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->auditLogger->log(
                AuditAction::Updated,
                auditable: $appointment,
                before: $before,
                after: [
                    'status' => $appointment->status->value,
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $endsAt->toIso8601String(),
                ],
                organization: $appointment->organization,
                unit: $appointment->unit,
            );

            return $appointment;
        });
    }
}
