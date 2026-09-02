<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IndexingPolicy;
use App\Models\Organization;
use App\Models\SiteGalleryItem;
use App\Models\SiteSetting;
use App\Support\Seo\CanonicalUrlResolver;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página pública "Ver todas" da galeria (botão "Ver todas" na landing —
 * ver LandingGallerySection.vue). Mesma regra de visibilidade da home:
 * só existe conteúdo enquanto o site estiver publicado.
 */
class PublicGalleryController extends Controller
{
    public function __construct(private readonly CanonicalUrlResolver $canonicalUrlResolver) {}

    public function index(): Response
    {
        $organization = Organization::query()->first();
        $siteSetting = SiteSetting::query()->first();
        $isPublished = $siteSetting && $siteSetting->is_published;

        $items = $isPublished
            ? SiteGalleryItem::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('id')
                ->paginate(24)
                ->through(fn (SiteGalleryItem $item) => [
                    'id' => $item->id,
                    'image_url' => Storage::disk('public')->url($item->image_path),
                    'caption' => $item->caption,
                    'alt_text' => $item->alt_text,
                ])
            : null;

        $robots = app()->isProduction()
            ? ($siteSetting ? $siteSetting->indexing_policy : IndexingPolicy::NoIndex)->metaRobots()
            : IndexingPolicy::NoIndex->metaRobots();

        return Inertia::render('Gallery', [
            'siteTitle' => $siteSetting?->title ?: (string) config('app.name'),
            'logoUrl' => $siteSetting?->logo_path ? Storage::disk('public')->url($siteSetting->logo_path) : null,
            'items' => $items,
            'seo' => [
                'title' => 'Galeria — '.($siteSetting?->title ?: (string) config('app.name')),
                'robots' => $robots,
                'canonical' => $this->canonicalUrlResolver->resolve($siteSetting, '/galeria'),
            ],
        ]);
    }
}
