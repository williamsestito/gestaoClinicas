<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteServiceRequest;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteServiceController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/services/Index', [
            'services' => SiteService::query()
                ->orderBy('order')
                ->orderBy('id')
                ->get()
                ->map(fn (SiteService $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'short_description' => $service->short_description,
                    'description' => $service->description,
                    'image_url' => $service->image_path ? Storage::disk('public')->url($service->image_path) : null,
                    'icon' => $service->icon,
                    'category' => $service->category,
                    'duration_minutes' => $service->duration_minutes,
                    'starting_price_cents' => $service->starting_price_cents,
                    'cta_text' => $service->cta_text,
                    'is_featured' => $service->is_featured,
                    'order' => $service->order,
                    'is_active' => $service->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(SiteServiceRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $service = new SiteService;
        $action->replaceImage($service, 'image_path', $request->file('image'), 'site-services');
        $action->upsert($service, $this->dataFromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço criado com sucesso.']);

        return back();
    }

    public function update(SiteServiceRequest $request, SiteService $siteService, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->replaceImage($siteService, 'image_path', $request->file('image'), 'site-services');
        $action->upsert($siteService, $this->dataFromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço atualizado com sucesso.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SiteService $siteService, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($siteService, 'image_path');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço excluído.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SiteService $siteService, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($siteService);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SiteService::class, $request->validated('ids'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFromRequest(SiteServiceRequest $request): array
    {
        $data = $request->safe()->except(['image', 'starting_price']);
        $price = $request->validated('starting_price');
        $data['starting_price_cents'] = $price !== null ? (int) round($price * 100) : null;

        return $data;
    }
}
