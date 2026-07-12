<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status genérico ativo/inativo, reutilizado por LegalEntity, Unit e
 * UnitMembership (entidades que nesta fase só precisam desses dois estados).
 */
enum RecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
        };
    }
}
