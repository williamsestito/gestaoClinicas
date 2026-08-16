<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            // Vínculo opcional de rastreabilidade com o agendamento real
            // gerado a partir deste lead (Etapa 3.2 do roadmap) — mesmo
            // padrão de vínculo simples (não FK composta) usado em
            // site_professionals.professional_id. Nulo enquanto o lead não
            // for convertido.
            $table->foreignUlid('appointment_id')->nullable()
                ->after('service_id')
                ->constrained('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn('appointment_id');
        });
    }
};
