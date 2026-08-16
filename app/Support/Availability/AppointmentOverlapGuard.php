<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Data\Availability\DailyAvailabilityInterval;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Unit;
use App\Services\Availability\ProfessionalAvailabilityResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garante que um agendamento não sobreponha outro compromisso real do
 * mesmo profissional, e que caiba dentro da disponibilidade operacional
 * dele na unidade (jornada regular menos ausências/bloqueios — reaproveita
 * App\Services\Availability\ProfessionalAvailabilityResolver sem alteração).
 * Sempre revalidado na Action, nunca confiando só no horário sugerido pelo
 * frontend.
 */
final class AppointmentOverlapGuard
{
    /** @var list<AppointmentStatus> Estados que efetivamente ocupam o horário. */
    private const BLOCKING_STATUSES = [
        AppointmentStatus::Requested,
        AppointmentStatus::AwaitingConfirmation,
        AppointmentStatus::Confirmed,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::InProgress,
    ];

    public static function assertNoConflict(
        Professional $professional,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $excludingId = null,
    ): void {
        // Advisory lock (liberado automaticamente no fim da transação) em
        // vez de `lockForUpdate()` sobre as linhas existentes: um
        // `SELECT ... FOR UPDATE` não trava nada quando o profissional
        // ainda não tem nenhum agendamento, permitindo que duas requisições
        // concorrentes para o primeiro horário dele passem pela checagem
        // antes de qualquer commit. O advisory lock serializa
        // independentemente de já existir linha.
        DB::statement('SELECT pg_advisory_xact_lock(?)', [crc32($professional->id)]);

        $query = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', array_map(fn (AppointmentStatus $status) => $status->value, self::BLOCKING_STATUSES))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Este horário já está ocupado por outro agendamento deste profissional.',
            ]);
        }
    }

    public static function assertWithinAvailability(
        Professional $professional,
        Unit $unit,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): void {
        $localStartsAt = $startsAt->copy()->setTimezone($unit->timezone);
        $localEndsAt = $endsAt->copy()->setTimezone($unit->timezone);

        $daily = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, $localStartsAt->copy()->startOfDay());

        $localStart = $localStartsAt->format('H:i');
        $localEnd = $localEndsAt->format('H:i');

        $fitsInsideAnInterval = collect($daily->effectiveIntervals)
            ->contains(fn (DailyAvailabilityInterval $interval) => $interval->startsAt <= $localStart && $interval->endsAt >= $localEnd);

        if (! $fitsInsideAnInterval) {
            throw ValidationException::withMessages([
                'starts_at' => 'Este horário está fora da disponibilidade do profissional.',
            ]);
        }
    }
}
