<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IndexingPolicy;
use App\Models\SiteSetting;
use App\Support\Seo\CanonicalUrlResolver;
use Illuminate\Http\Response;

/**
 * `robots.txt` e `sitemap.xml` — nunca usados como mecanismo de
 * segurança (rotas privadas continuam protegidas por autenticação);
 * apenas orientam rastreadores. Indexação em produção exige
 * `indexing_policy = index` explicitamente configurado pelo
 * administrador — nunca é o padrão.
 */
class SeoController extends Controller
{
    public function __construct(private readonly CanonicalUrlResolver $canonicalUrlResolver) {}

    public function robots(): Response
    {
        if (! $this->isIndexable()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
        }

        $siteSetting = SiteSetting::query()->first();
        $sitemapUrl = $this->canonicalUrlResolver->resolve($siteSetting, '/sitemap.xml');

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /confirm-password',
            'Disallow: /verify-email',
            'Disallow: /two-factor-challenge',
            'Disallow: /dashboard',
            'Disallow: /settings',
            'Disallow: /context',
            'Disallow: /onboarding',
            'Disallow: /admin',
            '',
            "Sitemap: {$sitemapUrl}",
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        if (! $this->isIndexable()) {
            return response('', 404);
        }

        $siteSetting = SiteSetting::query()->first();
        $lastmod = $siteSetting ? $siteSetting->updated_at : now();

        $urls = [[
            'loc' => $this->canonicalUrlResolver->resolve($siteSetting, '/'),
            'lastmod' => $lastmod->toAtomString(),
        ]];

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Indexação nunca é o padrão: exige produção E configuração
     * explícita do administrador ao mesmo tempo.
     */
    private function isIndexable(): bool
    {
        if (! app()->isProduction()) {
            return false;
        }

        $siteSetting = SiteSetting::query()->first();

        return $siteSetting?->indexing_policy === IndexingPolicy::Index;
    }
}
