<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adendo — única forma de corrigir um prontuário já finalizado (RN-007).
     * Sempre criado, nunca atualizado ou excluído: não existe rota de
     * update/destroy para este model. `professional_id` é o autor do
     * adendo, que pode ser diferente do autor original do prontuário (ex.:
     * um segundo profissional com `medical-records.manage` complementando o
     * registro).
     */
    public function up(): void
    {
        Schema::create('medical_record_addenda', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignUlid('medical_record_id')->constrained('medical_records')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['organization_id', 'medical_record_id']);

            $table->foreign(['organization_id', 'unit_id'], 'medical_record_addenda_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'medical_record_id'], 'medical_record_addenda_org_record_fk')
                ->references(['organization_id', 'id'])->on('medical_records')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'professional_id'], 'medical_record_addenda_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_addenda');
    }
};
