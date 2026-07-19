<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IndexingPolicy;
use App\Enums\SchemaBusinessType;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração singleton do conteúdo e SEO da página pública inicial
 * ("/"), administrada via o painel Filament ("Site e SEO"). Sempre há no
 * máximo uma linha. Dados de NAP (nome/endereço/telefone) não ficam
 * aqui — vêm de Organization/Unit/Address, a fonte normalizada única.
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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_type' => SchemaBusinessType::class,
            'indexing_policy' => IndexingPolicy::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
