<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\IndexingPolicy;
use App\Models\Organization;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Monta o pacote de metadados (título, descrição, canonical, robots,
 * Open Graph, Twitter Card e JSON-LD) enviado como prop `seo` para as
 * páginas Inertia. Renderizado no HTML inicial via app.blade.php — não
 * depende de execução tardia de JavaScript no navegador.
 */
final class SeoMetaBuilder
{
    public function __construct(
        private readonly CanonicalUrlResolver $canonicalUrlResolver,
        private readonly LocalBusinessJsonLd $localBusinessJsonLd,
    ) {}

    /** @return array<string, mixed> */
    public function forHome(?Organization $organization, ?SiteSetting $siteSetting): array
    {
        $title = $siteSetting?->meta_title ?: ($siteSetting?->title ?: (string) config('app.name'));
        $description = $siteSetting?->meta_description ?: $siteSetting?->description;
        $canonical = $this->canonicalUrlResolver->resolve($siteSetting, '/');
        $image = $siteSetting?->hero_image_path
            ? Storage::disk('public')->url($siteSetting->hero_image_path)
            : null;

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $this->resolveRobots($siteSetting),
            'og' => [
                'type' => 'website',
                'title' => $title,
                'description' => $description,
                'url' => $canonical,
                'image' => $image,
                'image_alt' => $siteSetting?->og_image_alt,
                'site_name' => $organization ? $organization->name : (string) config('app.name'),
                'locale' => 'pt_BR',
            ],
            'twitter' => [
                'card' => $image ? 'summary_large_image' : 'summary',
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'image_alt' => $siteSetting?->og_image_alt,
            ],
            'json_ld' => $organization
                ? $this->localBusinessJsonLd->build($organization, $siteSetting)
                : null,
        ];
    }

    /**
     * Ambientes fora de produção nunca são indexáveis, independentemente
     * do que o administrador configurar — é uma trava de segurança, não
     * uma preferência.
     */
    private function resolveRobots(?SiteSetting $siteSetting): string
    {
        if (! app()->isProduction()) {
            return IndexingPolicy::NoIndex->metaRobots();
        }

        $policy = $siteSetting ? $siteSetting->indexing_policy : IndexingPolicy::NoIndex;

        return $policy->metaRobots();
    }
}
