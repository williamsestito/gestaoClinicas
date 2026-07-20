<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteProfessionalRequest;
use App\Models\SiteProfessional;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteProfessionalController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/professionals/Index', [
            'professionals' => SiteProfessional::query()
                ->orderBy('order')
                ->get()
                ->map(fn (SiteProfessional $professional) => [
                    'id' => $professional->id,
                    'name' => $professional->name,
                    'role_title' => $professional->role_title,
                    'specialty' => $professional->specialty,
                    'professional_register' => $professional->professional_register,
                    'bio' => $professional->bio,
                    'photo_url' => $professional->photo_path ? Storage::disk('public')->url($professional->photo_path) : null,
                    'facebook_url' => $professional->facebook_url,
                    'instagram_url' => $professional->instagram_url,
                    'linkedin_url' => $professional->linkedin_url,
                    'order' => $professional->order,
                    'is_active' => $professional->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(SiteProfessionalRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $professional = new SiteProfessional;
        $action->replaceImage($professional, 'photo_path', $request->file('photo'), 'site-professionals');
        $action->upsert($professional, $request->safe()->except('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional criado com sucesso.']);

        return back();
    }

    public function update(SiteProfessionalRequest $request, SiteProfessional $siteProfessional, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->replaceImage($siteProfessional, 'photo_path', $request->file('photo'), 'site-professionals');
        $action->upsert($siteProfessional, $request->safe()->except('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional atualizado com sucesso.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SiteProfessional $siteProfessional, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($siteProfessional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional excluído.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SiteProfessional $siteProfessional, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($siteProfessional);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SiteProfessional::class, $request->validated('ids'));

        return back();
    }
}
