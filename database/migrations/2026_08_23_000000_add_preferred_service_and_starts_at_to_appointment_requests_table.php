<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo estruturado opcional com o serviço operacional real e o
     * horário exato escolhidos na busca pública de disponibilidade
     * (`LandingAvailabilitySearch.vue`) — mesmo racional de
     * `professional_id` (ver migration 2026_08_17_000000): antes disso,
     * só existia texto livre dentro de `notes`, impossível de consultar.
     * `service_id` continua sendo o catálogo público (SiteService, id
     * numérico, só para exibição no site) — nunca o mesmo espaço de id de
     * `preferred_service_id` (Service operacional, ULID).
     * `preferred_starts_at` é sempre UTC (mesma disciplina do resto do
     * agendamento real). `unit_id` já existe na tabela — não precisa de
     * nova coluna, só passa a receber a unidade real escolhida em vez de
     * sempre a matriz (ver PublicAppointmentRequestController::store()).
     */
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->foreignUlid('preferred_service_id')->nullable()->after('service_id')
                ->constrained('services')->nullOnDelete();
            $table->timestamp('preferred_starts_at')->nullable()->after('preferred_period');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->dropForeign(['preferred_service_id']);
            $table->dropColumn(['preferred_service_id', 'preferred_starts_at']);
        });
    }
};
