<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapa 5 (Comercial) — modo simplificado de formação de preço
     * (custo + margem desejada + desconto máximo). `default_price_cents`
     * já existente continua sendo o preço praticado.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('cost_cents')->nullable()->after('default_price_cents');
            $table->unsignedSmallInteger('margin_percentage')->nullable()->after('cost_cents');
            $table->unsignedSmallInteger('max_discount_percentage')->nullable()->after('margin_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['cost_cents', 'margin_percentage', 'max_discount_percentage']);
        });
    }
};
