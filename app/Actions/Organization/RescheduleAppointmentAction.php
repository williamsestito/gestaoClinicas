<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\AppointmentOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reagendamento atualiza a própria linha (sem duplicar registro) — o
 * histórico já é preservado pelo AuditLogger (before/after), mesmo
 * mecanismo usado por qualquer outra alteração do projeto.
 */
class RescheduleAppointmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Appointment $appointment, CarbonInterface $startsAt, CarbonInterface $endsAt): Appointment
    {
        if ($appointment->status->isFinal()) {
            throw ValidationException::withMessages([
                'appointment' => 'Não é possível reagendar um atendimento já concluído, cancelado ou não comparecido.',
            ]);
        }

        return DB::transaction(function () use ($appointment, $startsAt, $endsAt) {
            AppointmentOverlapGuard::assertWithinAvailability($appointment->professional, $appointment->unit, $startsAt, $endsAt);
            AppointmentOverlapGuard::assertNoConflict($appointment->professional, $startsAt, $endsAt, excludingId: $appointment->id);

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
                ],
                organization: $appointment->organization,
                unit: $appointment->unit,
            );

            return $appointment;
        });
    }
}
