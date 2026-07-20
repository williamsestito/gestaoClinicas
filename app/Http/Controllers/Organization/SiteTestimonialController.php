<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteTestimonialRequest;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteTestimonialController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/testimonials/Index', [
            'testimonials' => SiteTestimonial::query()
                ->orderBy('order')
                ->with('relatedService:id,name')
                ->get()
                ->map(fn (SiteTestimonial $testimonial) => [
                    'id' => $testimonial->id,
                    'author_name' => $testimonial->author_name,
                    'author_photo_url' => $testimonial->author_photo_path ? Storage::disk('public')->url($testimonial->author_photo_path) : null,
                    'rating' => $testimonial->rating,
                    'content' => $testimonial->content,
                    'related_service_id' => $testimonial->related_service_id,
                    'related_service_name' => $testimonial->relatedService?->name,
                    'is_featured' => $testimonial->is_featured,
                    'order' => $testimonial->order,
                    'is_active' => $testimonial->is_active,
                ])
                ->all(),
            'services' => SiteService::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SiteTestimonialRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $testimonial = new SiteTestimonial;
        $action->replaceImage($testimonial, 'author_photo_path', $request->file('photo'), 'site-testimonials');
        $action->upsert($testimonial, $request->safe()->except('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depoimento criado com sucesso.']);

        return back();
    }

    public function update(SiteTestimonialRequest $request, SiteTestimonial $siteTestimonial, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->replaceImage($siteTestimonial, 'author_photo_path', $request->file('photo'), 'site-testimonials');
        $action->upsert($siteTestimonial, $request->safe()->except('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depoimento atualizado com sucesso.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SiteTestimonial $siteTestimonial, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($siteTestimonial);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depoimento excluído.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SiteTestimonial $siteTestimonial, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($siteTestimonial);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SiteTestimonial::class, $request->validated('ids'));

        return back();
    }
}
