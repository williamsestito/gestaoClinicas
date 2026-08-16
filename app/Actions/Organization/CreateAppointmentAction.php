<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\AppointmentOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sempre revalida disponibilidade e conflito no servidor — nunca confia no
 * horário sugerido pelo frontend (App\Services\Availability\StaffAppointmentSlotFinder
 * é só uma sugestão, não autoritativa). Criado por staff, entra sempre em
 * `Confirmed` (ver docs/roadmap.md, Etapa 3.1).
 */
class CreateAppointmentAction
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
        ?string $notes = null,
    ): Appointment {
        return DB::transaction(function () use ($organization, $unit, $professional, $patient, $service, $startsAt, $endsAt, $notes) {
            AppointmentOverlapGuard::assertWithinAvailability($professional, $unit, $startsAt, $endsAt);
            AppointmentOverlapGuard::assertNoConflict($professional, $startsAt, $endsAt);

            $appointment = Appointment::query()->create([
                'organization_id' => $organization->id,
                'unit_id' => $unit->id,
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Confirmed,
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
                ],
                organization: $organization,
                unit: $unit,
            );

            return $appointment;
        });
    }
}
