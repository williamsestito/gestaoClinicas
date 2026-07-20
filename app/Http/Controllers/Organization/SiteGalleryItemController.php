<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteGalleryItemRequest;
use App\Models\SiteGalleryItem;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteGalleryItemController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/gallery/Index', [
            'items' => SiteGalleryItem::query()
                ->orderBy('order')
                ->get()
                ->map(fn (SiteGalleryItem $item) => [
                    'id' => $item->id,
                    'image_url' => Storage::disk('public')->url($item->image_path),
                    'caption' => $item->caption,
                    'alt_text' => $item->alt_text,
                    'category' => $item->category,
                    'is_cover' => $item->is_cover,
                    'order' => $item->order,
                    'is_active' => $item->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(SiteGalleryItemRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $item = new SiteGalleryItem;
        $action->replaceImage($item, 'image_path', $request->file('image'), 'site-gallery');
        $action->upsert($item, $request->safe()->except('image'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagem adicionada à galeria.']);

        return back();
    }

    public function update(SiteGalleryItemRequest $request, SiteGalleryItem $siteGalleryItem, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->replaceImage($siteGalleryItem, 'image_path', $request->file('image'), 'site-gallery');
        $action->upsert($siteGalleryItem, $request->safe()->except('image'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagem da galeria atualizada.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SiteGalleryItem $siteGalleryItem, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($siteGalleryItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagem removida da galeria.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SiteGalleryItem $siteGalleryItem, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($siteGalleryItem);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SiteGalleryItem::class, $request->validated('ids'));

        return back();
    }
}
