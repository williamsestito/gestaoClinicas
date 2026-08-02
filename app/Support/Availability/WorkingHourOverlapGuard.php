<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\Weekday;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garante que um profissional não tenha dois intervalos de jornada ativos
 * sobrepostos — nem na mesma unidade, nem entre unidades diferentes (um
 * profissional não pode estar em dois lugares ao mesmo tempo). Considera
 * simultaneamente dia da semana, horário e vigência; nunca só horário.
 *
 * As linhas relevantes são bloqueadas (`lockForUpdate`) antes da checagem,
 * dentro da transação chamada pela Action, para que duas requisições
 * concorrentes não consigam criar intervalos incompatíveis simultaneamente.
 */
final class WorkingHourOverlapGuard
{
    public static function assertNoConflict(
        ProfessionalUnit $professionalUnit,
        Weekday $weekday,
        string $startsAt,
        string $endsAt,
        ?string $effectiveFrom,
        ?string $effectiveUntil,
        ?string $excludingId = null,
    ): void {
        self::lockRelevantRows($professionalUnit);

        $sameUnitConflict = self::baseQuery($weekday, $startsAt, $endsAt, $effectiveFrom, $effectiveUntil, $excludingId)
            ->where('professional_unit_id', $professionalUnit->id)
            ->exists();

        if ($sameUnitConflict) {
            throw ValidationException::withMessages([
                'starts_at' => 'Este horário se sobrepõe a outro intervalo do profissional.',
            ]);
        }

        $otherUnitIds = ProfessionalUnit::query()
            ->where('professional_id', $professionalUnit->professional_id)
            ->where('id', '!=', $professionalUnit->id)
            ->pluck('id');

        if ($otherUnitIds->isEmpty()) {
            return;
        }

        $crossUnitConflict = self::baseQuery($weekday, $startsAt, $endsAt, $effectiveFrom, $effectiveUntil, $excludingId)
            ->whereIn('professional_unit_id', $otherUnitIds)
            ->exists();

        if ($crossUnitConflict) {
            throw ValidationException::withMessages([
                'starts_at' => 'O profissional já possui jornada em outra unidade neste período.',
            ]);
        }
    }

    /**
     * Vigências [effective_from, effective_until] se cruzam quando:
     * (existente sem início OU novo sem fim OU existente.início <= novo.fim)
     * E
     * (existente sem fim OU novo sem início OU existente.fim >= novo.início)
     * Limites nulos representam intervalo aberto (sempre já começou / nunca
     * termina) — quando o novo intervalo é aberto de um lado, essa metade
     * da condição é sempre verdadeira e não precisa de filtro.
     */
    /** @return Builder<ProfessionalWorkingHour> */
    private static function baseQuery(
        Weekday $weekday,
        string $startsAt,
        string $endsAt,
        ?string $effectiveFrom,
        ?string $effectiveUntil,
        ?string $excludingId,
    ): Builder {
        $query = ProfessionalWorkingHour::query()
            ->where('weekday', $weekday->value)
            ->where('status', 'active')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($effectiveUntil !== null) {
            $query->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $effectiveUntil));
        }

        if ($effectiveFrom !== null) {
            $query->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $effectiveFrom));
        }

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        return $query;
    }

    private static function lockRelevantRows(ProfessionalUnit $professionalUnit): void
    {
        $unitIds = ProfessionalUnit::query()
            ->where('professional_id', $professionalUnit->professional_id)
            ->pluck('id');

        DB::table('professional_working_hours')
            ->whereIn('professional_unit_id', $unitIds)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->get();
    }
}
