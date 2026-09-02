<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Disponibilidade de um serviço por unidade — dimensão independente do
 * status ativo/inativo do serviço (App\Enums\RecordStatus). Um serviço
 * pode estar ativo e ainda assim indisponível para agendamento em
 * qualquer unidade, até ser explicitamente configurado.
 */
enum ServiceAvailabilityScope: string
{
    case AllUnits = 'all_units';
    case SelectedUnits = 'selected_units';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::AllUnits => 'Todas as unidades',
            self::SelectedUnits => 'Unidades selecionadas',
            self::None => 'Indisponível',
        };
    }
}
