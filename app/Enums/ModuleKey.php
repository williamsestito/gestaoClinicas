<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Módulos de negócio habilitáveis por organização (ver docs/roadmap.md,
 * Etapa 1). `Core` nunca é desabilitável — não existe linha correspondente
 * em `organization_modules`, `Organization::hasModule()` já retorna `true`
 * para ele sem consultar o banco.
 */
enum ModuleKey: string
{
    case Core = 'core';
    case Dental = 'dental';
    case Aesthetics = 'aesthetics';
    case Medical = 'medical';
    case Beauty = 'beauty';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Núcleo (sempre ativo)',
            self::Dental => 'Odontologia',
            self::Aesthetics => 'Estética',
            self::Medical => 'Médico',
            self::Beauty => 'Centro de beleza',
        };
    }

    public function isCore(): bool
    {
        return $this === self::Core;
    }

    /** @return list<self> Módulos exibidos como toggle na tela de configuração. */
    public static function toggleable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $key) => ! $key->isCore()));
    }
}
