<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ProfessionalService;

/**
 * Sincroniza os vínculos de unidade de um professional_service com a lista
 * final desejada (IDs já validados pelo Form Request). Vínculos removidos
 * são apenas excluídos logicamente (preservando histórico); vínculos novos
 * são criados. Mesmo padrão de App\Support\ServiceLinkSynchronizer, usado
 * por AssignServiceToProfessionalAction/UpdateProfessionalServiceAssignmentAction
 * dentro de uma transação.
 */
final class ProfessionalServiceUnitSynchronizer
{
    /** @param array<int, string> $unitIds */
    public static function sync(ProfessionalService $link, array $unitIds): void
    {
        $current = $link->unitLinks()->pluck('unit_id')->all();

        $toRemove = array_diff($current, $unitIds);
        $toAdd = array_diff($unitIds, $current);

        if ($toRemove !== []) {
            $link->unitLinks()->whereIn('unit_id', $toRemove)->get()->each->delete();
        }

        foreach ($toAdd as $unitId) {
            $link->unitLinks()->create([
                'organization_id' => $link->organization_id,
                'unit_id' => $unitId,
            ]);
        }
    }
}
