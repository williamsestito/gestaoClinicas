<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporte para foreign key composta a partir de
     * professional_working_hours (Etapa 2.8) — mesmo padrão já usado para
     * specialties/services/professionals/units desde a Etapa 2.1.
     */
    public function up(): void
    {
        Schema::table('professional_unit', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('professional_unit', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
