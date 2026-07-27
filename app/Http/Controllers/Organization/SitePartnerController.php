<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SitePartnerRequest;
use App\Models\SitePartner;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SitePartnerController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/partners/Index', [
            'partners' => SitePartner::query()
                ->orderBy('order')
                ->orderBy('id')
                ->get()
                ->map(fn (SitePartner $partner) => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'logo_url' => $partner->logo_path ? Storage::disk('public')->url($partner->logo_path) : null,
                    'url' => $partner->url,
                    'order' => $partner->order,
                    'is_active' => $partner->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(SitePartnerRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $partner = new SitePartner;
        $action->replaceImage($partner, 'logo_path', $request->file('logo'), 'site-partners');
        $action->upsert($partner, $request->safe()->except('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Parceiro adicionado com sucesso.']);

        return back();
    }

    public function update(SitePartnerRequest $request, SitePartner $sitePartner, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->replaceImage($sitePartner, 'logo_path', $request->file('logo'), 'site-partners');
        $action->upsert($sitePartner, $request->safe()->except('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Parceiro atualizado com sucesso.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SitePartner $sitePartner, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($sitePartner, 'logo_path');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Parceiro removido.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SitePartner $sitePartner, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($sitePartner);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SitePartner::class, $request->validated('ids'));

        return back();
    }
}
