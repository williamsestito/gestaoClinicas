<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relacionamento entre serviço e especialidade, pendente da Etapa 2.1
     * (registrado explicitamente no relatório daquela etapa) e agora
     * necessário para a Etapa 2.3. Mesmo padrão de professional_specialty:
     * organization_id redundante para permitir FK composta anti-cruzamento,
     * soft delete com índice único parcial que preserva histórico.
     */
    public function up(): void
    {
        Schema::create('service_specialty', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignUlid('specialty_id')->constrained('specialties')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id']);
            $table->index(['specialty_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX service_specialty_unique_active_link
             ON service_specialty (service_id, specialty_id)
             WHERE deleted_at IS NULL',
        );

        DB::statement(
            'ALTER TABLE service_specialty ADD CONSTRAINT ss_org_service_fk
             FOREIGN KEY (organization_id, service_id) REFERENCES services (organization_id, id)
             ON DELETE RESTRICT',
        );

        DB::statement(
            'ALTER TABLE service_specialty ADD CONSTRAINT ss_org_specialty_fk
             FOREIGN KEY (organization_id, specialty_id) REFERENCES specialties (organization_id, id)
             ON DELETE RESTRICT',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('service_specialty');
    }
};
