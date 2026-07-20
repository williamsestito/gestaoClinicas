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
            // Site da clínica
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->text('about_text')->nullable();
            // Endereço/telefone/WhatsApp/e-mail públicos NÃO são
            // duplicados aqui — vêm sempre da unidade matriz (Unit/Address),
            // a fonte normalizada única (consistência de NAP, ver
            // docs/architecture/seo.md). Esta página só exibe/linka para lá.
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('footer_text')->nullable();
            $table->boolean('is_published')->default(false);

            // Marketing — apenas configuração armazenada; nenhum script é
            // ativado automaticamente (ver docs/architecture/seo.md).
            $table->string('integration_method')->default('none');
            $table->string('ga4_measurement_id')->nullable();
            $table->boolean('ga4_enabled')->default(false);
            $table->string('gtm_container_id')->nullable();
            $table->boolean('gtm_enabled')->default(false);
            $table->string('google_ads_conversion_id')->nullable();
            $table->string('google_ads_conversion_label')->nullable();
            $table->boolean('google_ads_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path', 'favicon_path', 'cta_text', 'cta_url', 'about_text',
                'facebook_url', 'instagram_url', 'linkedin_url', 'footer_text', 'is_published',
                'integration_method', 'ga4_measurement_id', 'ga4_enabled',
                'gtm_container_id', 'gtm_enabled', 'google_ads_conversion_id',
                'google_ads_conversion_label', 'google_ads_enabled',
            ]);
        });
    }
};
