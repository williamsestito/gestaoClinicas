<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação operacional resumida de um profissional — nunca persistida,
 * sempre derivada (ver App\Services\Professionals\ProfessionalOperationalStatusResolver).
 * Não é um booleano simples porque a interface precisa explicar o motivo
 * ("configuração incompleta" é diferente de "profissional inativo").
 */
enum ProfessionalOperationalStatus: string
{
    case Operational = 'operational';
    case Incomplete = 'incomplete';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operacional',
            self::Incomplete => 'Configuração incompleta',
            self::Inactive => 'Inativo',
        };
    }
}
