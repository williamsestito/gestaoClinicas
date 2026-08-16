<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pacote de sessões (Etapa 3.3 do roadmap) — só contagem de sessões,
     * sem preço/pagamento (Comercial/Financeiro ainda não existem, Etapas
     * 5/6). "Restantes" nunca é uma coluna persistida — sempre calculado a
     * partir de agendamentos concluídos vinculados
     * (App\Models\SessionPackage::remainingSessions()), para nunca
     * dessincronizar.
     */
    public function up(): void
    {
        Schema::create('session_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->unsignedSmallInteger('total_sessions');
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'patient_id']);

            $table->foreign(['organization_id', 'patient_id'], 'session_packages_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'service_id'], 'session_packages_org_service_fk')
                ->references(['organization_id', 'id'])->on('services')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE session_packages ADD CONSTRAINT session_packages_total_sessions_positive CHECK (total_sessions > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('session_packages');
    }
};
