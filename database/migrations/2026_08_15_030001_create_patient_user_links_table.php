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
        // Vinculo entre conta de portal e paciente(s) que ela gerencia
        // (titular e/ou dependentes) — modelo proprio, nao pivot Laravel,
        // mesmo padrao de professional_unit. Ver docs/modules/patient-portal.md.
        Schema::create('patient_user_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Redundante em relacao a patient_user_id/patient_id, mas
            // necessario para as FKs compostas abaixo bloquearem no banco o
            // cruzamento entre organizacoes diferentes (mesmo padrao de
            // patient_responsibles).
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('patient_user_id')->constrained('patient_users')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['patient_user_id', 'patient_id']);

            $table->foreign(['organization_id', 'patient_user_id'], 'patient_user_links_org_account_fk')
                ->references(['organization_id', 'id'])->on('patient_users')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'patient_id'], 'patient_user_links_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();
        });

        // No maximo um vinculo "self" ativo por conta.
        DB::statement(
            "CREATE UNIQUE INDEX patient_user_links_unique_active_self
             ON patient_user_links (patient_user_id)
             WHERE role = 'self' AND deleted_at IS NULL",
        );

        // No maximo uma conta de portal ativa por paciente (limitacao aceita
        // de MVP: um segundo responsavel nao tem login proprio nesta etapa,
        // ver docs/modules/patient-portal.md).
        DB::statement(
            'CREATE UNIQUE INDEX patient_user_links_unique_active_patient
             ON patient_user_links (patient_id)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_user_links');
    }
};
