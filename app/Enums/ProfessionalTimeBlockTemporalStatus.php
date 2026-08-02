<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação temporal calculada de um bloqueio a partir de `starts_at`/
 * `ends_at`, `status` e `deleted_at` — nunca persistida, sempre derivada
 * (ver App\Models\ProfessionalTimeBlock::temporalStatus()).
 */
enum ProfessionalTimeBlockTemporalStatus: string
{
    case Future = 'future';
    case Ongoing = 'ongoing';
    case Ended = 'ended';
    case Inactive = 'inactive';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Future => 'Futuro',
            self::Ongoing => 'Em andamento',
            self::Ended => 'Encerrado',
            self::Inactive => 'Inativo',
            self::Deleted => 'Excluído',
        };
    }
}
