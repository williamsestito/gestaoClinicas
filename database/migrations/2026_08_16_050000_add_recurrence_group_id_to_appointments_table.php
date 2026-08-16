<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrupador visual de uma série de agendamentos recorrentes (Etapa 3.3
     * do roadmap) — apenas um ULID comum gravado em cada ocorrência gerada
     * na mesma criação, sem FK (não é uma entidade própria, só uma "regra
     * viva"; cada ocorrência já nasce como uma linha independente e
     * totalmente gerenciável isolada — cancelar/reagendar uma não afeta as
     * demais).
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->ulid('recurrence_group_id')->nullable()->after('session_package_id');
            $table->index('recurrence_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('recurrence_group_id');
        });
    }
};
