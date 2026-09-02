<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\Unit;
use App\Support\Availability\LocalTimeConverter;
use Carbon\CarbonInterface;

/**
 * Intervalos já ocupados de um profissional num dia — agendamentos reais
 * (`Appointment`, nos status que efetivamente ocupam a agenda) **e**
 * pré-agendamentos pendentes (`AppointmentRequest` com horário exato
 * escolhido na busca de disponibilidade, ainda não contatado/cancelado/
 * convertido). Usado tanto pelo buscador público (landing, anônimo) quanto
 * pelo do staff/portal autenticado — nenhum dos dois deve sugerir, nem
 * permitir confirmar, um horário que outro paciente já pediu ou já tem
 * reservado.
 *
 * Achado em uso real: dois pré-agendamentos pendentes para o mesmo
 * profissional no mesmo horário exato nunca eram impedidos — só o segundo,
 * ao tentar CONVERTER para `Appointment`, esbarrava no conflito. Isso
 * deixava a recepção descobrir tarde demais que só um dos dois pacientes
 * podia ser atendido.
 */
final class BookedRangeResolver
{
    /** @var list<AppointmentStatus> Estados que efetivamente ocupam o horário. */
    private const APPOINTMENT_BLOCKING_STATUSES = [
        AppointmentStatus::Requested,
        AppointmentStatus::AwaitingConfirmation,
        AppointmentStatus::Confirmed,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::InProgress,
    ];

    /** @var list<AppointmentRequestStatus> Pré-agendamentos ainda "em aberto". */
    private const REQUEST_BLOCKING_STATUSES = [
        AppointmentRequestStatus::Pending,
        AppointmentRequestStatus::Contacted,
    ];

    /**
     * @return array<int, array{0: string, 1: string}> horários locais
     *                                                 "H:i" [início, fim)
     */
    public function forProfessionalOnDate(Professional $professional, Unit $unit, CarbonInterface $date, ?string $excludingAppointmentRequestId = null): array
    {
        $dayStart = LocalTimeConverter::startOfLocalDayInUtc($date->toDateString(), $unit->timezone);
        $dayEnd = LocalTimeConverter::startOfNextLocalDayInUtc($date->toDateString(), $unit->timezone);

        $appointmentRanges = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', array_map(fn (AppointmentStatus $status) => $status->value, self::APPOINTMENT_BLOCKING_STATUSES))
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment) => [
                $appointment->starts_at->copy()->setTimezone($unit->timezone)->format('H:i'),
                $appointment->ends_at->copy()->setTimezone($unit->timezone)->format('H:i'),
            ]);

        $requestRanges = AppointmentRequest::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', array_map(fn (AppointmentRequestStatus $status) => $status->value, self::REQUEST_BLOCKING_STATUSES))
            ->whereNotNull('preferred_starts_at')
            ->whereNotNull('preferred_service_id')
            ->where('preferred_starts_at', '<', $dayEnd)
            ->where('preferred_starts_at', '>=', $dayStart)
            ->when($excludingAppointmentRequestId !== null, fn ($query) => $query->where('id', '!=', $excludingAppointmentRequestId))
            ->get(['preferred_starts_at', 'preferred_service_id'])
            ->map(function (AppointmentRequest $request) use ($professional, $unit) {
                $duration = ProfessionalService::query()
                    ->where('organization_id', $professional->organization_id)
                    ->where('professional_id', $professional->id)
                    ->where('service_id', $request->preferred_service_id)
                    ->first()
                    ?->effectiveDurationMinutes() ?? 30;

                $startsAt = $request->preferred_starts_at->copy()->setTimezone($unit->timezone);

                return [
                    $startsAt->format('H:i'),
                    $startsAt->copy()->addMinutes($duration)->format('H:i'),
                ];
            });

        // `->merge()` não serve aqui: um `Eloquent\Collection` mapeado para
        // arrays simples ainda tenta se comportar como coleção de models ao
        // fundir (chama `$item->getKey()` internamente) — concatenação
        // simples dos arrays já resolvidos evita isso.
        return [...$appointmentRanges->all(), ...$requestRanges->all()];
    }

    /** @param  array<int, array{0: string, 1: string}>  $ranges */
    public static function overlapsAny(string $start, string $end, array $ranges): bool
    {
        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($start < $rangeEnd && $end > $rangeStart) {
                return true;
            }
        }

        return false;
    }
}
