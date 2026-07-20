<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Escolha única de mecanismo de integração de marketing — evita instalar
 * Google Tag e Google Tag Manager simultaneamente sem estratégia definida
 * (duplicaria eventos/conversões).
 */
enum IntegrationMethod: string
{
    case None = 'none';
    case GoogleTag = 'gtag';
    case GoogleTagManager = 'gtm';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nenhum',
            self::GoogleTag => 'Google Tag (gtag.js)',
            self::GoogleTagManager => 'Google Tag Manager',
        };
    }
}
