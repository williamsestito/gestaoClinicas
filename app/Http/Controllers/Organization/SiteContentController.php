<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

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
                'logo_url' => $siteSetting->logo_path ? Storage::disk('public')->url($siteSetting->logo_path) : null,
                'favicon_url' => $siteSetting->favicon_path ? Storage::disk('public')->url($siteSetting->favicon_path) : null,
                'primary_color' => $siteSetting->primary_color,
                'secondary_color' => $siteSetting->secondary_color,
                'cta_text' => $siteSetting->cta_text,
                'cta_url' => $siteSetting->cta_url,
                'cta_secondary_text' => $siteSetting->cta_secondary_text,
                'cta_secondary_url' => $siteSetting->cta_secondary_url,
                'about_text' => $siteSetting->about_text,
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
        $data = $request->safe()->except(['hero_image', 'logo', 'favicon']);

        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            data: $data,
            files: [
                'hero_image' => $request->file('hero_image'),
                'logo' => $request->file('logo'),
                'favicon' => $request->file('favicon'),
            ],
            organization: $tenant->organization(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site atualizado com sucesso.']);

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
