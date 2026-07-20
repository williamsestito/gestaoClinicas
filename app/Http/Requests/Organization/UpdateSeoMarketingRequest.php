<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\IndexingPolicy;
use App\Enums\IntegrationMethod;
use App\Enums\SchemaBusinessType;
use App\Models\SiteSetting;
use App\Rules\OfficialDomainRule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeoMarketingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('updateSeo', [SiteSetting::class, $organization]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'official_domain' => ['nullable', 'string', new OfficialDomainRule, 'max:253'],
            'schema_type' => ['required', Rule::enum(SchemaBusinessType::class)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'og_image_alt' => ['nullable', 'string', 'max:255'],
            'focus_keywords' => ['nullable', 'string', 'max:1000'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'indexing_policy' => ['required', Rule::enum(IndexingPolicy::class)],
            'google_search_console_verification' => ['nullable', 'string', 'max:255'],
            'bing_webmaster_verification' => ['nullable', 'string', 'max:255'],
            'google_business_profile_url' => ['nullable', 'url', 'max:255'],
            'google_reviews_url' => ['nullable', 'url', 'max:255'],
            'google_maps_url' => ['nullable', 'url', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'integration_method' => ['required', Rule::enum(IntegrationMethod::class)],
            'ga4_measurement_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]+$/'],
            'ga4_enabled' => ['boolean'],
            'gtm_container_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]+$/'],
            'gtm_enabled' => ['boolean'],
            'google_ads_conversion_id' => ['nullable', 'string', 'regex:/^AW-[0-9]+$/'],
            'google_ads_conversion_label' => ['nullable', 'string', 'max:255'],
            'google_ads_enabled' => ['boolean'],
        ];
    }
}
