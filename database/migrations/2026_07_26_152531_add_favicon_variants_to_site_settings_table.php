<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Tamanhos gerados automaticamente a partir de hero_image
            // enviada pelo administrador (ver App\Support\Site\
            // FaviconGenerator) — nunca editado diretamente pelo usuário.
            $table->json('favicon_variants')->nullable()->after('favicon_path');
            // Banner dedicado para telas pequenas — quando ausente, a
            // landing usa hero_image_path também no mobile (mesmo
            // comportamento de antes desta coluna existir).
            $table->string('hero_image_mobile_path')->nullable()->after('hero_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['favicon_variants', 'hero_image_mobile_path']);
        });
    }
};
