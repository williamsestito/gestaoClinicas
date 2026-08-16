<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo opcional com o pacote de sessões descontado por este
     * agendamento (Etapa 3.3). `nullOnDelete` — perder o pacote nunca
     * apaga o histórico do atendimento.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignUlid('session_package_id')->nullable()
                ->after('service_id')
                ->constrained('session_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['session_package_id']);
            $table->dropColumn('session_package_id');
        });
    }
};
