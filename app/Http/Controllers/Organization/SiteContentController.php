<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\RemoveSiteAssetAction;
use App\Actions\Organization\SetSitePublishedStatusAction;
use App\Actions\Organization\UpdateSiteContentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateSiteContentRequest;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteContentController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $this->authorize('view', [SiteSetting::class, $organization]);

        $siteSetting = SiteSetting::query()->first();
        $headquarters = $organization?->headquarters()->with('address')->first();

        return Inertia::render('settings/site/Index', [
            'site' => $siteSetting ? [
                'title' => $siteSetting->title,
                'description' => $siteSetting->description,
                'hero_image_url' => $siteSetting->hero_image_path ? Storage::disk('public')->url($siteSetting->hero_image_path) : null,
                'hero_image_mobile_url' => $siteSetting->hero_image_mobile_path ? Storage::disk('public')->url($siteSetting->hero_image_mobile_path) : null,
                'logo_url' => $siteSetting->logo_path ? Storage::disk('public')->url($siteSetting->logo_path) : null,
                'favicon_url' => $siteSetting->favicon_path ? Storage::disk('public')->url($siteSetting->favicon_path) : null,
                'favicon_urls' => $siteSetting->faviconUrls(),
                'primary_color' => $siteSetting->primary_color,
                'secondary_color' => $siteSetting->secondary_color,
                'cta_text' => $siteSetting->cta_text,
                'cta_url' => $siteSetting->cta_url,
                'cta_secondary_text' => $siteSetting->cta_secondary_text,
                'cta_secondary_url' => $siteSetting->cta_secondary_url,
                'about_text' => $siteSetting->about_text,
                'mission_text' => $siteSetting->mission_text,
                'vision_text' => $siteSetting->vision_text,
                'facebook_url' => $siteSetting->facebook_url,
                'instagram_url' => $siteSetting->instagram_url,
                'linkedin_url' => $siteSetting->linkedin_url,
                'footer_text' => $siteSetting->footer_text,
                'is_published' => $siteSetting->is_published,
            ] : null,
            'contact' => $headquarters ? [
                'phone' => $headquarters->phone,
                'whatsapp' => $headquarters->whatsapp,
                'email' => $headquarters->email,
                'address' => $headquarters->address ? [
                    'street' => $headquarters->address->street,
                    'number' => $headquarters->address->number,
                    'city' => $headquarters->address->city,
                    'state' => $headquarters->address->state,
                ] : null,
            ] : null,
        ]);
    }

    public function update(UpdateSiteContentRequest $request, TenantContext $tenant, UpdateSiteContentAction $action): RedirectResponse
    {
        $data = $request->safe()->except(['hero_image', 'hero_image_mobile', 'logo', 'favicon']);

        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            data: $data,
            files: [
                'hero_image' => $request->file('hero_image'),
                'hero_image_mobile' => $request->file('hero_image_mobile'),
                'logo' => $request->file('logo'),
                'favicon' => $request->file('favicon'),
            ],
            organization: $tenant->organization(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site atualizado com sucesso.']);

        return back();
    }

    public function destroyHeroImage(TenantContext $tenant, RemoveSiteAssetAction $action): RedirectResponse
    {
        $organization = $tenant->organization();

        $this->authorize('update', [SiteSetting::class, $organization]);

        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            column: 'hero_image_path',
            errorKey: 'hero_image',
            missingContentMessage: 'Configure o conteúdo do site antes de remover o banner.',
            organization: $organization,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Banner removido.']);

        return back();
    }

    public function destroyHeroImageMobile(TenantContext $tenant, RemoveSiteAssetAction $action): RedirectResponse
    {
        $organization = $tenant->organization();

        $this->authorize('update', [SiteSetting::class, $organization]);

        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            column: 'hero_image_mobile_path',
            errorKey: 'hero_image_mobile',
            missingContentMessage: 'Configure o conteúdo do site antes de remover o banner mobile.',
            organization: $organization,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Banner mobile removido.']);

        return back();
    }

    public function destroyLogo(TenantContext $tenant, RemoveSiteAssetAction $action): RedirectResponse
    {
        $organization = $tenant->organization();

        $this->authorize('update', [SiteSetting::class, $organization]);

        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            column: 'logo_path',
            errorKey: 'logo',
            missingContentMessage: 'Configure o conteúdo do site antes de remover o logotipo.',
            organization: $organization,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logotipo removido.']);

        return back();
    }

    public function destroyFavicon(TenantContext $tenant, RemoveSiteAssetAction $action): RedirectResponse
    {
        $organization = $tenant->organization();

        $this->authorize('update', [SiteSetting::class, $organization]);

        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            column: 'favicon_path',
            errorKey: 'favicon',
            missingContentMessage: 'Configure o conteúdo do site antes de remover o favicon.',
            organization: $organization,
            variantsColumn: 'favicon_variants',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Favicon removido.']);

        return back();
    }

    public function publish(TenantContext $tenant, SetSitePublishedStatusAction $action): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('publish', [SiteSetting::class, $organization]);

        $action->handle(SiteSetting::query()->first(), true, $organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site publicado com sucesso.']);

        return back();
    }

    public function unpublish(TenantContext $tenant, SetSitePublishedStatusAction $action): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('publish', [SiteSetting::class, $organization]);

        $action->handle(SiteSetting::query()->first(), false, $organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site despublicado.']);

        return back();
    }
}
