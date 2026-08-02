<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Em quais unidades um vínculo profissional-serviço se aplica — dimensão
 * independente do status ativo/inativo do vínculo. Mesmo padrão de três
 * estados de App\Enums\ServiceAvailabilityScope, para não introduzir uma
 * segunda estratégia concorrente no domínio.
 */
enum ProfessionalServiceUnitScope: string
{
    case AllCompatibleUnits = 'all_compatible_units';
    case SelectedUnits = 'selected_units';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::AllCompatibleUnits => 'Todas as unidades compatíveis',
            self::SelectedUnits => 'Unidades selecionadas',
            self::None => 'Nenhuma unidade',
        };
    }
}
