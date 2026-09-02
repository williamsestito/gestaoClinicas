<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rastreia que um pacote nasceu de uma venda (Etapa 5). Pacotes
     * criados manualmente (fluxo já existente da Etapa 3.3) continuam com
     * este campo nulo, comportamento intacto.
     */
    public function up(): void
    {
        Schema::table('session_packages', function (Blueprint $table) {
            $table->foreignUlid('origin_sale_item_id')->nullable()->after('service_id')
                ->constrained('sale_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_packages', function (Blueprint $table) {
            $table->dropForeign(['origin_sale_item_id']);
            $table->dropColumn('origin_sale_item_id');
        });
    }
};
