<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloqueia no banco o cruzamento entre organizações diferentes: o par
     * (organization_id, professional_id) deve existir em professionals, e o
     * par (organization_id, service_id) deve existir em services — um
     * profissional da organização A nunca pode ser vinculado a um serviço da
     * organização B, mesmo que ambos os IDs sejam válidos isoladamente.
     */
    public function up(): void
    {
        Schema::table('professional_service', function (Blueprint $table) {
            $table->foreign(['organization_id', 'professional_id'], 'psv_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'service_id'], 'psv_org_service_fk')
                ->references(['organization_id', 'id'])->on('services')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('professional_service', function (Blueprint $table) {
            $table->dropForeign('psv_org_professional_fk');
            $table->dropForeign('psv_org_service_fk');
        });
    }
};
