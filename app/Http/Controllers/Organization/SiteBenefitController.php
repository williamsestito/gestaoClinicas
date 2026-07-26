<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteBenefitRequest;
use App\Models\SiteBenefit;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteBenefitController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/benefits/Index', [
            'benefits' => SiteBenefit::query()
                ->orderBy('order')
                ->orderBy('id')
                ->get()
                ->map(fn (SiteBenefit $benefit) => [
                    'id' => $benefit->id,
                    'icon' => $benefit->icon,
                    'title' => $benefit->title,
                    'description' => $benefit->description,
                    'order' => $benefit->order,
                    'is_active' => $benefit->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(SiteBenefitRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->upsert(new SiteBenefit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Benefício criado com sucesso.']);

        return back();
    }

    public function update(SiteBenefitRequest $request, SiteBenefit $siteBenefit, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->upsert($siteBenefit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Benefício atualizado com sucesso.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SiteBenefit $siteBenefit, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($siteBenefit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Benefício excluído.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SiteBenefit $siteBenefit, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($siteBenefit);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SiteBenefit::class, $request->validated('ids'));

        return back();
    }
}
