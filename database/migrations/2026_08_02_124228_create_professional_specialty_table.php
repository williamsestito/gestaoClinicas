<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_specialty', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Redundante em relacao a professional_id/specialty_id, mas necessario
            // para bloquear no banco o cruzamento entre organizacoes diferentes
            // (constraint composta abaixo) sem depender de join em tempo de escrita.
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('specialty_id')->constrained('specialties')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'status']);
            $table->index(['specialty_id', 'status']);
        });

        // Nao permite duplicidade ativa (mesmo par profissional/especialidade
        // vinculado mais de uma vez ao mesmo tempo), mas preserva o historico —
        // depois de um soft delete, o par pode ser vinculado novamente.
        DB::statement(
            'CREATE UNIQUE INDEX professional_specialty_unique_active_link
             ON professional_specialty (professional_id, specialty_id)
             WHERE deleted_at IS NULL',
        );

        // No maximo uma especialidade principal ativa por profissional.
        DB::statement(
            'CREATE UNIQUE INDEX professional_specialty_one_primary_per_professional
             ON professional_specialty (professional_id)
             WHERE is_primary = true AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_specialty');
    }
};
