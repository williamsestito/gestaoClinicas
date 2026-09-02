<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurso compartilhado (sala/equipamento) — Etapa 3.3 do roadmap.
     * Pertence a exatamente uma unidade (FK obrigatória `unit_id`), nunca a
     * "zero ou mais" unidades — por isso não usa um enum de `unit_scope`
     * como Service/ProfessionalService (esses existem para vínculos
     * opcionais entre organização e unidade, não para pertencimento
     * obrigatório e único). Índices únicos já nascem parciais (ignoram
     * `deleted_at`) — Specialty só recebeu essa correção depois, como
     * patch; aqui entra certo desde o início.
     */
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['unit_id']);

            $table->foreign(['organization_id', 'unit_id'], 'resources_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX resources_org_unit_name_unique
             ON resources (organization_id, unit_id, name)
             WHERE deleted_at IS NULL',
        );

        // Suporte para FK composta a partir de appointment_resource.
        DB::statement('ALTER TABLE resources ADD CONSTRAINT resources_org_id_unique UNIQUE (organization_id, id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
