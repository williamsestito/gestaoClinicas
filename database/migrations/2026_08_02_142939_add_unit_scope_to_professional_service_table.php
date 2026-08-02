<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correção da fundação: a Etapa 2.1 não previu como restringir por
     * unidade o vínculo profissional-serviço (pendência registrada
     * explicitamente naquele relatório). Segue o mesmo padrão de três
     * estados já usado em services.unit_availability_scope, para não
     * introduzir uma segunda estratégia concorrente: todas as unidades
     * compatíveis (padrão — nenhuma linha em professional_service_unit é
     * necessária), apenas unidades selecionadas (linhas na tabela), ou
     * nenhuma unidade operacional.
     */
    public function up(): void
    {
        Schema::table('professional_service', function (Blueprint $table) {
            $table->string('unit_scope')->default('all_compatible_units')->after('custom_buffer_after_minutes');
        });

        DB::statement(
            "ALTER TABLE professional_service ADD CONSTRAINT professional_service_unit_scope_valid
             CHECK (unit_scope IN ('all_compatible_units', 'selected_units', 'none'))",
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE professional_service DROP CONSTRAINT professional_service_unit_scope_valid');

        Schema::table('professional_service', function (Blueprint $table) {
            $table->dropColumn('unit_scope');
        });
    }
};
