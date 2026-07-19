<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Domínio e identificação
            $table->string('official_domain')->nullable()->after('secondary_color');
            $table->string('schema_type')->default('LocalBusiness')->after('official_domain');

            // Metadados de SEO
            $table->string('meta_title')->nullable()->after('schema_type');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('og_image_alt')->nullable()->after('meta_description');
            $table->text('focus_keywords')->nullable()->after('og_image_alt');
            $table->string('author_name')->nullable()->after('focus_keywords');
            $table->string('indexing_policy')->default('noindex')->after('author_name');

            // Verificações de ferramentas de busca (token/conteúdo específico, nunca HTML livre)
            $table->string('google_search_console_verification')->nullable()->after('indexing_policy');
            $table->string('bing_webmaster_verification')->nullable()->after('google_search_console_verification');

            // Presença no Google Business Profile (somente links, sem consumir API)
            $table->string('google_business_profile_url')->nullable()->after('bing_webmaster_verification');
            $table->string('google_reviews_url')->nullable()->after('google_business_profile_url');
            $table->string('google_maps_url')->nullable()->after('google_reviews_url');

            // Coordenadas opcionais para o JSON-LD (GeoCoordinates)
            $table->decimal('latitude', 10, 7)->nullable()->after('google_maps_url');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
