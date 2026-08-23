<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pré-requisito para `medical_records.appointment_id` referenciar
     * `appointments` via FK composta `(organization_id, id)` (mesmo padrão
     * já usado por `units`/`professionals`/`patients`/`services` — ver
     * `units_organization_id_id_unique`) — o Postgres exige um índice único
     * exatamente sobre essas duas colunas para aceitar a FK composta. Só
     * adiciona o índice; não altera nenhum comportamento existente.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
