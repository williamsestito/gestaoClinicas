<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\CopyProfessionalPublicDataAction;
use App\Actions\Site\LinkSiteProfessionalToProfessionalAction;
use App\Actions\Site\SiteCollectionItemAction;
use App\Actions\Site\UnlinkSiteProfessionalAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CopySiteProfessionalDataRequest;
use App\Http\Requests\Organization\LinkSiteProfessionalRequest;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteProfessionalRequest;
use App\Models\Professional;
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
                ->with('professional:id,display_name,status,deleted_at')
                ->orderBy('order')
                ->orderBy('id')
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
                    'professional_id' => $professional->professional_id,
                    'linked_professional' => $professional->professional === null ? null : [
                        'id' => $professional->professional->id,
                        'name' => $professional->professional->display_name,
                        'is_operational' => $professional->professional->status === RecordStatus::Active
                            && $professional->professional->deleted_at === null,
                    ],
                ])
                ->all(),
            'operationalProfessionals' => $organization
                ->professionals()
                ->where('status', RecordStatus::Active)
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (Professional $professional) => ['id' => $professional->id, 'name' => $professional->display_name])
                ->all(),
        ]);
    }

    public function link(LinkSiteProfessionalRequest $request, SiteProfessional $siteProfessional, TenantContext $tenant, LinkSiteProfessionalToProfessionalAction $action): RedirectResponse
    {
        $organization = $tenant->organization();
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail((string) $request->validated('professional_id'));

        $action->handle($siteProfessional, $professional, $organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional operacional vinculado com sucesso.']);

        return back();
    }

    public function unlink(TenantContext $tenant, SiteProfessional $siteProfessional, UnlinkSiteProfessionalAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->handle($siteProfessional, $tenant->organization());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'O vínculo foi removido. O conteúdo público foi preservado.']);

        return back();
    }

    public function copyPublicData(CopySiteProfessionalDataRequest $request, SiteProfessional $siteProfessional, TenantContext $tenant, CopyProfessionalPublicDataAction $action): RedirectResponse
    {
        abort_if($siteProfessional->professional_id === null, 422, 'Este item não está vinculado a um profissional operacional.');

        $organization = $tenant->organization();
        $professional = Professional::query()->where('organization_id', $organization->id)->findOrFail($siteProfessional->professional_id);

        $action->handle($siteProfessional, $professional, $organization, $request->validated('fields'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Os dados públicos selecionados foram atualizados. Nenhum dado interno foi publicado.']);

        return back();
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

        $action->delete($siteProfessional, 'photo_path');

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
