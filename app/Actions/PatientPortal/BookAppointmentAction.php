<?php

declare(strict_types=1);

namespace App\Actions\PatientPortal;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\AppointmentOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Booking real feito pelo próprio paciente (Etapa 3.2 do roadmap) — mesma
 * checagem de conflito de App\Actions\Organization\CreateAppointmentAction
 * (App\Support\Availability\AppointmentOverlapGuard, sem alteração), mas
 * entra em `Requested`, não `Confirmed`: a recepção confirma manualmente
 * (App\Actions\Organization\ConfirmAppointmentAction). `AuditLog.actor_user_id`
 * é FK só para App\Models\User (staff) — como quem agendou foi um
 * App\Models\PatientUser, o vínculo fica no `after` (jsonb), não no schema.
 */
class BookAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(
        Organization $organization,
        Unit $unit,
        Professional $professional,
        Patient $patient,
        Service $service,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        PatientUser $patientUser,
        ?string $notes = null,
    ): Appointment {
        return DB::transaction(function () use ($organization, $unit, $professional, $patient, $service, $startsAt, $endsAt, $patientUser, $notes) {
            AppointmentOverlapGuard::assertWithinAvailability($professional, $unit, $startsAt, $endsAt);
            $hadConflict = AppointmentOverlapGuard::assertNoConflict($professional, $startsAt, $endsAt, allowOverlap: $organization->allow_appointment_overlap);

            $appointment = Appointment::query()->create([
                'organization_id' => $organization->id,
                'unit_id' => $unit->id,
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Requested,
                'notes' => $notes,
            ]);

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $appointment,
                after: [
                    'professional_id' => $professional->id,
                    'patient_id' => $patient->id,
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $endsAt->toIso8601String(),
                    'status' => $appointment->status->value,
                    'booked_by' => 'patient_portal',
                    'patient_user_id' => $patientUser->id,
                ],
                organization: $organization,
                unit: $unit,
            );

            if ($hadConflict) {
                $this->auditLogger->log(
                    AuditAction::ConflictDetected,
                    auditable: $appointment,
                    after: ['professional_id' => $professional->id, 'starts_at' => $startsAt->toIso8601String()],
                    organization: $organization,
                    unit: $unit,
                );
            }

            return $appointment;
        });
    }
}
