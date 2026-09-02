<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * Sincroniza os vínculos de um serviço com especialidades e unidades a
 * partir da lista final desejada. O Form Request do fluxo Inertia já
 * valida que cada id pertence à mesma organização, mas esta classe
 * revalida de novo aqui — é a única forma de garantir a regra também para
 * chamadores que não passam pelo Form Request (ex.: o formulário do
 * Filament, que só restringe as opções exibidas, sem impedir que um
 * payload adulterado tente enviar um id de outra organização). Vínculos
 * removidos são apenas excluídos logicamente (preservando histórico);
 * vínculos novos são criados. Usado por
 * CreateServiceAction/UpdateServiceAction dentro de uma transação.
 */
final class ServiceLinkSynchronizer
{
    /** @param array<int, string> $specialtyIds */
    public static function syncSpecialties(Service $service, array $specialtyIds): void
    {
        if ($specialtyIds !== []) {
            $validCount = Specialty::query()
                ->where('organization_id', $service->organization_id)
                ->whereIn('id', $specialtyIds)
                ->count();

            if ($validCount !== count(array_unique($specialtyIds))) {
                throw ValidationException::withMessages([
                    'specialty_ids' => 'Uma das especialidades selecionadas não pertence a esta clínica.',
                ]);
            }
        }

        $current = $service->specialtyLinks()->pluck('specialty_id')->all();

        $toRemove = array_diff($current, $specialtyIds);
        $toAdd = array_diff($specialtyIds, $current);

        if ($toRemove !== []) {
            $service->specialtyLinks()->whereIn('specialty_id', $toRemove)->get()->each->delete();
        }

        foreach ($toAdd as $specialtyId) {
            $service->specialtyLinks()->create([
                'organization_id' => $service->organization_id,
                'specialty_id' => $specialtyId,
            ]);
        }
    }

    /** @param array<int, string> $unitIds */
    public static function syncUnits(Service $service, array $unitIds): void
    {
        if ($unitIds !== []) {
            $validCount = Unit::query()
                ->where('organization_id', $service->organization_id)
                ->whereIn('id', $unitIds)
                ->count();

            if ($validCount !== count(array_unique($unitIds))) {
                throw ValidationException::withMessages([
                    'unit_ids' => 'Uma das unidades selecionadas não pertence a esta clínica.',
                ]);
            }
        }

        $current = $service->unitLinks()->pluck('unit_id')->all();

        $toRemove = array_diff($current, $unitIds);
        $toAdd = array_diff($unitIds, $current);

        if ($toRemove !== []) {
            $service->unitLinks()->whereIn('unit_id', $toRemove)->get()->each->delete();
        }

        foreach ($toAdd as $unitId) {
            $service->unitLinks()->create([
                'organization_id' => $service->organization_id,
                'unit_id' => $unitId,
            ]);
        }
    }
}
