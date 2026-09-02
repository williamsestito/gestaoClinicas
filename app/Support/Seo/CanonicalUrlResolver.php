<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\SiteSetting;

/**
 * Única fonte de geração de URLs absolutas para fins de SEO (canonical,
 * sitemap, Open Graph, JSON-LD). Nunca concatene domínio manualmente em
 * outro lugar — sempre passe por aqui.
 *
 * Regra: em produção, usa o domínio oficial configurado (HTTPS); fora de
 * produção — ou quando nenhum domínio foi configurado — usa `APP_URL`,
 * para nunca depender de um domínio de produção fixo no código.
 */
final class CanonicalUrlResolver
{
    public function resolve(?SiteSetting $siteSetting, string $path = '/'): string
    {
        $domain = $siteSetting?->official_domain;
        $base = (app()->isProduction() && filled($domain))
            ? "https://{$domain}"
            : rtrim((string) config('app.url'), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
