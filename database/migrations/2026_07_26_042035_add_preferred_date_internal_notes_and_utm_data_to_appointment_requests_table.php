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
            // Preferência de data do visitante — nunca um horário confirmado
            // nem verificado contra disponibilidade real (ver Fase 0, Etapa 0.6).
            $table->date('preferred_date')->nullable()->after('preferred_period');

            // Observação da equipe sobre o contato — nunca exibida na landing
            // pública, distinta da mensagem enviada pelo visitante (`notes`).
            $table->text('internal_notes')->nullable()->after('notes');

            // Parâmetros utm_*, referrer e URL de origem capturados no
            // envio do formulário — apenas para análise de origem, nunca
            // usados para autorização ou execução de código.
            $table->json('utm_data')->nullable()->after('internal_notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->dropColumn(['preferred_date', 'internal_notes', 'utm_data']);
        });
    }
};
