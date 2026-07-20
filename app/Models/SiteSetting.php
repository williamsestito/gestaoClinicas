<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IndexingPolicy;
use App\Enums\IntegrationMethod;
use App\Enums\SchemaBusinessType;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração singleton do conteúdo e SEO da página pública inicial
 * ("/"), administrada via o painel Filament ("Site e SEO") ou pelas
 * páginas Vue "Site da clínica"/"SEO e marketing". Sempre há no máximo
 * uma linha. Dados de NAP (nome/endereço/telefone) não ficam aqui — vêm
 * de Organization/Unit/Address, a fonte normalizada única.
 *
 * @property string|null $official_domain
 * @property SchemaBusinessType $schema_type
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $og_image_alt
 * @property string|null $google_business_profile_url
 * @property string|null $google_reviews_url
 * @property string|null $google_maps_url
 * @property IndexingPolicy $indexing_policy
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $logo_path
 * @property string|null $favicon_path
 * @property string|null $cta_text
 * @property string|null $cta_url
 * @property string|null $about_text
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $linkedin_url
 * @property string|null $footer_text
 * @property bool $is_published
 * @property list<array{type: string, active: bool}>|null $sections_config
 * @property string|null $cta_secondary_text
 * @property string|null $cta_secondary_url
 * @property IntegrationMethod $integration_method
 * @property string|null $ga4_measurement_id
 * @property bool $ga4_enabled
 * @property string|null $gtm_container_id
 * @property bool $gtm_enabled
 * @property string|null $google_ads_conversion_id
 * @property string|null $google_ads_conversion_label
 * @property bool $google_ads_enabled
 */
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'hero_image_path',
        'primary_color',
        'secondary_color',
        'official_domain',
        'schema_type',
        'meta_title',
        'meta_description',
        'og_image_alt',
        'focus_keywords',
        'author_name',
        'indexing_policy',
        'google_search_console_verification',
        'bing_webmaster_verification',
        'google_business_profile_url',
        'google_reviews_url',
        'google_maps_url',
        'latitude',
        'longitude',
        'logo_path',
        'favicon_path',
        'cta_text',
        'cta_url',
        'about_text',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'footer_text',
        'is_published',
        'sections_config',
        'cta_secondary_text',
        'cta_secondary_url',
        'integration_method',
        'ga4_measurement_id',
        'ga4_enabled',
        'gtm_container_id',
        'gtm_enabled',
        'google_ads_conversion_id',
        'google_ads_conversion_label',
        'google_ads_enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_type' => SchemaBusinessType::class,
            'indexing_policy' => IndexingPolicy::class,
            'integration_method' => IntegrationMethod::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_published' => 'boolean',
            'sections_config' => 'array',
            'ga4_enabled' => 'boolean',
            'gtm_enabled' => 'boolean',
            'google_ads_enabled' => 'boolean',
        ];
    }
}
