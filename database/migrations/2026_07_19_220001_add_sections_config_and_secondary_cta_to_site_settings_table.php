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
            // Ordem e ativação das seções da landing page pública. Formato:
            // lista ordenada de {type, active} — ver App\Support\Site\LandingSections,
            // que normaliza este campo com segurança contra tipos desconhecidos
            // ou ausentes (sempre há um valor padrão para cada tipo conhecido).
            $table->json('sections_config')->nullable();
            $table->string('cta_secondary_text')->nullable();
            $table->string('cta_secondary_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['sections_config', 'cta_secondary_text', 'cta_secondary_url']);
        });
    }
};
