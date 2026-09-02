<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\AppointmentStatus;
use App\Models\SharedResource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garante que um recurso compartilhado (sala/equipamento) não seja
 * reservado em dois agendamentos com horários sobrepostos — mesmo formato
 * de App\Support\Availability\AppointmentOverlapGuard, mas por
 * `resource_id` em vez de `professional_id`. Nunca dispensado pelo toggle
 * de "encaixe" (App\Enums\Organization::$allow_appointment_overlap) — esse
 * toggle relaxa apenas a agenda humana do profissional; duas pessoas não
 * podem usar a mesma sala fisicamente ao mesmo tempo.
 */
final class ResourceOverlapGuard
{
    /** @var list<AppointmentStatus> Estados que efetivamente ocupam o recurso. */
    private const BLOCKING_STATUSES = [
        AppointmentStatus::Requested,
        AppointmentStatus::AwaitingConfirmation,
        AppointmentStatus::Confirmed,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::InProgress,
    ];

    public static function assertNoConflict(
        SharedResource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $excludingAppointmentId = null,
    ): void {
        // Mesmo racional de advisory lock de AppointmentOverlapGuard: evita
        // janela de corrida quando o recurso ainda não tem nenhum
        // agendamento (um FOR UPDATE não travaria nada nesse caso).
        DB::statement('SELECT pg_advisory_xact_lock(?)', [crc32($resource->id)]);

        $query = $resource->appointments()
            ->whereIn('appointments.status', array_map(fn (AppointmentStatus $status) => $status->value, self::BLOCKING_STATUSES))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludingAppointmentId !== null) {
            $query->where('appointments.id', '!=', $excludingAppointmentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'resource_ids' => "O recurso \"{$resource->name}\" já está reservado para outro agendamento neste horário.",
            ]);
        }
    }
}
