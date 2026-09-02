<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\ProfessionalTimeBlockScope;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garante que um profissional não tenha dois bloqueios ativos sobrepostos
 * quando os escopos se cruzam: um bloqueio "todas as unidades" sobrepõe
 * qualquer bloqueio do profissional no período; um bloqueio de unidade
 * específica só sobrepõe bloqueios "todas as unidades" ou da mesma
 * unidade. Bloqueios em unidades diferentes podem coexistir. Nesta fase,
 * qualquer sobreposição de escopos que se cruzam é bloqueada (não há
 * mesclagem automática).
 */
final class TimeBlockOverlapGuard
{
    public static function assertNoConflict(
        Professional $professional,
        ProfessionalTimeBlockScope $scope,
        ?string $unitId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $excludingId = null,
    ): void {
        DB::table('professional_time_blocks')
            ->where('professional_id', $professional->id)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->get();

        $query = ProfessionalTimeBlock::query()
            ->where('professional_id', $professional->id)
            ->where('status', 'active')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($scope === ProfessionalTimeBlockScope::SpecificUnit) {
            $query->where(fn ($q) => $q->where('scope', ProfessionalTimeBlockScope::AllUnits->value)
                ->orWhere(fn ($qq) => $qq->where('scope', ProfessionalTimeBlockScope::SpecificUnit->value)->where('unit_id', $unitId)));
        }

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_date' => 'Este período se sobrepõe a outro bloqueio.',
            ]);
        }
    }
}
