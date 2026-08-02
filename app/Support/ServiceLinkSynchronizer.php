<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Service;

/**
 * Sincroniza os vínculos de um serviço com especialidades e unidades a
 * partir da lista final desejada (IDs já validados pelo Form Request contra
 * a mesma organização). Vínculos removidos são apenas excluídos logicamente
 * (preservando histórico); vínculos novos são criados. Usado por
 * CreateServiceAction/UpdateServiceAction dentro de uma transação.
 */
final class ServiceLinkSynchronizer
{
    /** @param array<int, string> $specialtyIds */
    public static function syncSpecialties(Service $service, array $specialtyIds): void
    {
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
