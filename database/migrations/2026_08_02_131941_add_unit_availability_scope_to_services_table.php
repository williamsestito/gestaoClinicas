<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diferencia explicitamente as três estratégias de disponibilidade por
     * unidade pedidas na Etapa 2.3 (nenhuma delas existia na Etapa 2.1):
     * disponível em todas as unidades (padrão — nenhuma linha em
     * service_unit é necessária), disponível somente nas unidades
     * selecionadas (linhas em service_unit) ou indisponível (nenhuma
     * unidade pode agendar, independente do status ativo/inativo do
     * serviço, que é uma dimensão diferente).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('unit_availability_scope')->default('all_units')->after('requires_manual_confirmation');
        });

        DB::statement(
            "ALTER TABLE services ADD CONSTRAINT services_unit_availability_scope_valid
             CHECK (unit_availability_scope IN ('all_units', 'selected_units', 'none'))",
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE services DROP CONSTRAINT services_unit_availability_scope_valid');

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('unit_availability_scope');
        });
    }
};
