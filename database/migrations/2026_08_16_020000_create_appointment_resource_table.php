<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivô muitos-para-muitos entre Appointment e Resource — um agendamento
     * pode usar zero ou mais recursos (salas/equipamentos) simultaneamente.
     * Chave primária composta (sem `id` próprio): diferente de
     * professional_specialty/service_specialty (modelos reais, com Eloquent
     * ->create()), este pivô é gravado via belongsToMany()->sync()/attach(),
     * que não gera ULID para uma coluna `id` fora do fluxo normal de
     * criação de model — chave composta é o padrão correto aqui.
     * FK composta com `resources(organization_id, id)` bloqueia cruzamento
     * entre organizações, mesmo padrão de professional_service.
     */
    public function up(): void
    {
        Schema::create('appointment_resource', function (Blueprint $table) {
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUlid('resource_id')->constrained('resources')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['appointment_id', 'resource_id']);
            $table->index(['resource_id']);

            $table->foreign(['organization_id', 'resource_id'], 'appointment_resource_org_resource_fk')
                ->references(['organization_id', 'id'])->on('resources')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_resource');
    }
};
