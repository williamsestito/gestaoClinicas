<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidades onde um serviço está disponível, usado somente quando
     * services.unit_availability_scope = 'selected_units'. Pendente da
     * Etapa 2.1 (registrado no relatório daquela etapa) e agora necessário
     * para a Etapa 2.3. Mesmo padrão de professional_unit.
     */
    public function up(): void
    {
        Schema::create('service_unit', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id']);
            $table->index(['unit_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX service_unit_unique_active_link
             ON service_unit (service_id, unit_id)
             WHERE deleted_at IS NULL',
        );

        DB::statement(
            'ALTER TABLE service_unit ADD CONSTRAINT su_org_service_fk
             FOREIGN KEY (organization_id, service_id) REFERENCES services (organization_id, id)
             ON DELETE RESTRICT',
        );

        DB::statement(
            'ALTER TABLE service_unit ADD CONSTRAINT su_org_unit_fk
             FOREIGN KEY (organization_id, unit_id) REFERENCES units (organization_id, id)
             ON DELETE RESTRICT',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('service_unit');
    }
};
