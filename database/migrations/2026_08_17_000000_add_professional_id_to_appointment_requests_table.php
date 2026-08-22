<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo estruturado opcional com o profissional escolhido na busca
     * pública de disponibilidade (ou no card de um profissional na
     * landing) — antes disso, a única referência a um profissional era
     * texto livre dentro de `notes`, impossível de consultar (ver
     * docs/modules/public-integration.md). `nullOnDelete`: perder o
     * profissional nunca deveria apagar o histórico da solicitação.
     */
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->foreignUlid('professional_id')->nullable()->after('service_id')
                ->constrained('professionals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            // dropConstrainedForeignId() pressupõe uma coluna bigint —
            // professional_id é ULID, então a FK e a coluna precisam ser
            // removidas explicitamente.
            $table->dropForeign(['professional_id']);
            $table->dropColumn('professional_id');
        });
    }
};
