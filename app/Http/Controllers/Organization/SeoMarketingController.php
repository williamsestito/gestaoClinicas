<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\UpdateSeoMarketingAction;
use App\Enums\IndexingPolicy;
use App\Enums\IntegrationMethod;
use App\Enums\SchemaBusinessType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateSeoMarketingRequest;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SeoMarketingController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $this->authorize('viewSeo', [SiteSetting::class, $organization]);

        $siteSetting = SiteSetting::query()->first();

        return Inertia::render('settings/seo/Index', [
            'seo' => $siteSetting?->only([
                'official_domain', 'schema_type', 'meta_title', 'meta_description',
                'og_image_alt', 'focus_keywords', 'author_name', 'indexing_policy',
                'google_search_console_verification', 'bing_webmaster_verification',
                'google_business_profile_url', 'google_reviews_url', 'google_maps_url',
                'latitude', 'longitude',
            ]),
            'marketing' => $siteSetting?->only([
                'integration_method', 'ga4_measurement_id', 'ga4_enabled',
                'gtm_container_id', 'gtm_enabled', 'google_ads_conversion_id',
                'google_ads_conversion_label', 'google_ads_enabled',
            ]),
            'schemaTypeOptions' => collect(SchemaBusinessType::cases())
                ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            'indexingPolicyOptions' => collect(IndexingPolicy::cases())
                ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            'integrationMethodOptions' => collect(IntegrationMethod::cases())
                ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
        ]);
    }

    public function update(UpdateSeoMarketingRequest $request, TenantContext $tenant, UpdateSeoMarketingAction $action): RedirectResponse
    {
        $action->handle(
            siteSetting: SiteSetting::query()->first(),
            data: $request->validated(),
            organization: $tenant->organization(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'SEO e marketing atualizados com sucesso.']);

        return back();
    }
}
