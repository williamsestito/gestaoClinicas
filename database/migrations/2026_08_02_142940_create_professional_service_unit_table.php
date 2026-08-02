<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidades específicas onde um vínculo profissional-serviço se aplica —
     * usado somente quando professional_service.unit_scope =
     * 'selected_units'. A unidade informada ainda precisa estar na
     * interseção entre as unidades de atuação do profissional e as
     * unidades onde o serviço está disponível — isso é responsabilidade da
     * Action/Request (não é expressável como constraint de banco).
     */
    public function up(): void
    {
        Schema::create('professional_service_unit', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_service_id')->constrained('professional_service')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_service_id']);
            $table->index(['unit_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX professional_service_unit_unique_active_link
             ON professional_service_unit (professional_service_id, unit_id)
             WHERE deleted_at IS NULL',
        );

        DB::statement(
            'ALTER TABLE professional_service_unit ADD CONSTRAINT psu_org_service_link_fk
             FOREIGN KEY (organization_id, professional_service_id) REFERENCES professional_service (organization_id, id)
             ON DELETE RESTRICT',
        );

        DB::statement(
            'ALTER TABLE professional_service_unit ADD CONSTRAINT psu_org_unit_fk
             FOREIGN KEY (organization_id, unit_id) REFERENCES units (organization_id, id)
             ON DELETE RESTRICT',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_service_unit');
    }
};
