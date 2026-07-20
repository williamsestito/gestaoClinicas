<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\UpdateSiteSectionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateSiteSectionsRequest;
use App\Models\SiteSetting;
use App\Support\Site\LandingSections;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteSectionsController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        $siteSetting = SiteSetting::query()->first();

        return Inertia::render('settings/site/sections/Index', [
            'sections' => LandingSections::normalize($siteSetting?->sections_config),
        ]);
    }

    public function update(UpdateSiteSectionsRequest $request, TenantContext $tenant, UpdateSiteSectionsAction $action): RedirectResponse
    {
        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            sections: $request->validated('sections'),
            organization: $tenant->organization(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ordem das seções atualizada.']);

        return back();
    }
}
