<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Escopo explícito de um bloqueio — nunca inferido de `unit_id` nulo.
 * `AllUnits` sempre tem `unit_id` nulo; `SpecificUnit` sempre tem `unit_id`
 * preenchido, garantido por CHECK no banco.
 */
enum ProfessionalTimeBlockScope: string
{
    case AllUnits = 'all_units';
    case SpecificUnit = 'specific_unit';

    public function label(): string
    {
        return match ($this) {
            self::AllUnits => 'Todas as unidades',
            self::SpecificUnit => 'Unidade específica',
        };
    }
}
