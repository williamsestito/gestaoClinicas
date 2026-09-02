<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decisão explícita de publicação no site público — nasce `false` para
     * todo profissional existente, nunca publicando automaticamente sem
     * consentimento administrativo. Ver docs/modules/public-integration.md.
     */
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
