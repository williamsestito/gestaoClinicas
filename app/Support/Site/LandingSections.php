<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * Catálogo fechado dos tipos de seção da landing pública e normalização do
 * campo `site_settings.sections_config` (ordem + ativação). Nunca deixa a
 * aplicação quebrar por causa de um tipo desconhecido ou ausente no JSON
 * armazenado — tipos desconhecidos são descartados e tipos conhecidos que
 * faltarem são adicionados ao final, ativos por padrão.
 */
final class LandingSections
{
    /** @var list<string> */
    public const TYPES = [
        'hero',
        'statistics',
        'about',
        'services',
        'professionals',
        'benefits',
        'gallery',
        'testimonials',
        'partners',
        'scheduling',
        'cta',
        'faq',
        'contact',
    ];

    /**
     * @param  mixed  $stored  valor bruto vindo de `sections_config` (json decodificado)
     * @return list<array{type: string, active: bool}>
     */
    public static function normalize(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];

        $result = [];
        foreach ($stored as $entry) {
            $type = is_array($entry) ? ($entry['type'] ?? null) : null;

            if (is_string($type) && in_array($type, self::TYPES, true)) {
                $result[] = ['type' => $type, 'active' => (bool) ($entry['active'] ?? true)];
            }
        }

        $present = array_column($result, 'type');
        foreach (self::TYPES as $type) {
            if (! in_array($type, $present, true)) {
                $result[] = ['type' => $type, 'active' => true];
            }
        }

        return $result;
    }
}
