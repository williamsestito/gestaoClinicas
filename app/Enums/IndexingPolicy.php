<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Controla se as páginas públicas podem ser indexadas por buscadores.
 * O padrão é sempre NoIndex — indexação em produção exige opt-in
 * explícito do administrador (nunca habilitada automaticamente).
 */
enum IndexingPolicy: string
{
    case Index = 'index';
    case NoIndex = 'noindex';

    public function label(): string
    {
        return match ($this) {
            self::Index => 'Permitir indexação (index, follow)',
            self::NoIndex => 'Bloquear indexação (noindex, nofollow)',
        };
    }

    /** Valor pronto para a meta tag `robots`/cabeçalho `X-Robots-Tag`. */
    public function metaRobots(): string
    {
        return match ($this) {
            self::Index => 'index, follow',
            self::NoIndex => 'noindex, nofollow',
        };
    }
}
