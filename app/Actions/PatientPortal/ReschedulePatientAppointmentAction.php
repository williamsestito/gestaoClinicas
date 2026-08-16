<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\PatientUser;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\AppointmentOverlapGuard;
use App\Support\Availability\ResourceOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reagendamento pelo próprio paciente/dependente (Etapa 3.3 do roadmap) —
 * reaproveita App\Support\Availability\AppointmentOverlapGuard sem
 * alteração, mesmo formato de App\Actions\Organization\RescheduleAppointmentAction
 * staff. Sem prazo mínimo de antecedência nesta etapa (nenhuma regra
 * escrita para isso — decisão registrada em docs/modules/patient-portal.md).
 */
class ReschedulePatientAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment, PatientUser $patientUser, CarbonInterface $startsAt, CarbonInterface $endsAt): Appointment
    {
        if ($appointment->status->isFinal()) {
            throw ValidationException::withMessages([
                'appointment' => 'Não é possível reagendar um atendimento já concluído, cancelado ou não comparecido.',
            ]);
        }

        return DB::transaction(function () use ($appointment, $patientUser, $startsAt, $endsAt) {
            AppointmentOverlapGuard::assertWithinAvailability($appointment->professional, $appointment->unit, $startsAt, $endsAt);
            AppointmentOverlapGuard::assertNoConflict(
                $appointment->professional,
                $startsAt,
                $endsAt,
                excludingId: $appointment->id,
                allowOverlap: $appointment->organization->allow_appointment_overlap,
            );

            foreach ($appointment->resources as $resource) {
                ResourceOverlapGuard::assertNoConflict($resource, $startsAt, $endsAt, excludingAppointmentId: $appointment->id);
            }

            $before = [
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
            ];

            $appointment->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);

            $this->auditLogger->log(
                AuditAction::Updated,
                auditable: $appointment,
                before: $before,
                after: [
                    'starts_at' => $appointment->starts_at->toIso8601String(),
                    'ends_at' => $appointment->ends_at->toIso8601String(),
                    'rescheduled_by' => 'patient_portal',
                    'patient_user_id' => $patientUser->id,
                ],
                organization: $appointment->organization,
                unit: $appointment->unit,
            );

            return $appointment;
        });
    }
}
