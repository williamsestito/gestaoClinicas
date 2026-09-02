<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prontuário clínico (Etapa 4 do roadmap) — um por atendimento
     * (`appointment_id` único). Rascunho (`draft`) é editável; uma vez
     * `finalized`, os campos clínicos nunca são atualizados de novo (só
     * `medical_record_addenda` registra correções) nem excluídos — por isso
     * este model nunca usa SoftDeletes, não existe rota de exclusão alguma
     * (RN-007 do documento de visão: "prontuário finalizado não pode ser
     * excluído ou sobrescrito; correções usam adendo ou versão").
     * `released_to_patient_at` é deliberadamente separado de `finalized_at`
     * — um registro pode estar finalizado sem ainda estar liberado para o
     * portal do paciente (RN-014: "paciente só visualiza registros
     * finalizados e liberados").
     */
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->string('status')->default('draft');

            $table->text('anamnesis')->nullable();
            $table->text('preexisting_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('evaluation')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('procedures_performed')->nullable();
            $table->text('evolution_notes')->nullable();
            $table->text('prescriptions')->nullable();
            $table->text('referrals')->nullable();
            // Campos extras por especialidade (Estética/Beleza — Seção 10.3
            // do documento de visão), sem alterar o núcleo de dados (Seção
            // 18) — nenhum form-builder genérico existe ou é necessário
            // para o escopo desta etapa.
            $table->jsonb('specialty_data')->nullable();

            $table->boolean('has_return_right')->default(false);
            $table->unsignedSmallInteger('return_window_days')->nullable();

            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('released_to_patient_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'patient_id']);
            $table->index(['organization_id', 'professional_id']);
            // Necessário para que medical_record_addenda/medical_record_files
            // possam referenciar (organization_id, id) via FK composta —
            // mesmo padrão de units_organization_id_id_unique.
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'unit_id'], 'medical_records_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'patient_id'], 'medical_records_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'professional_id'], 'medical_records_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'appointment_id'], 'medical_records_org_appointment_fk')
                ->references(['organization_id', 'id'])->on('appointments')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE medical_records ADD CONSTRAINT medical_records_status_valid
             CHECK (status IN (\'draft\', \'finalized\'))',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
