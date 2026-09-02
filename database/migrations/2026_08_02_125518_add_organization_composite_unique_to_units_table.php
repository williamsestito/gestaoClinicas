<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Constraint auxiliar (redundante em relação à PK `id`, já globalmente
     * única) exigida para que outras tabelas do domínio de profissionais
     * (ex.: professional_unit) possam referenciar `units` através de uma
     * foreign key composta `(organization_id, unit_id)` — bloqueando no
     * próprio banco o cruzamento de uma unidade com uma organização diferente
     * da que ela pertence, sem depender apenas de validação em Action/frontend.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
