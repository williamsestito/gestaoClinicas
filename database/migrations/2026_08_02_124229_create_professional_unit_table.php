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
        // Onde o profissional ATUA (clinicamente) — nao confundir com
        // UnitMembership, que representa onde um USUARIO pode ACESSAR o sistema.
        // Os dois conceitos sao independentes e nao devem ser fundidos.
        Schema::create('professional_unit', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'status']);
            $table->index(['unit_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE professional_unit ADD CONSTRAINT professional_unit_ends_after_starts
             CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)',
        );

        DB::statement(
            'CREATE UNIQUE INDEX professional_unit_unique_active_link
             ON professional_unit (professional_id, unit_id)
             WHERE deleted_at IS NULL',
        );

        // No maximo uma unidade principal ativa por profissional.
        DB::statement(
            'CREATE UNIQUE INDEX professional_unit_one_primary_per_professional
             ON professional_unit (professional_id)
             WHERE is_primary = true AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_unit');
    }
};
