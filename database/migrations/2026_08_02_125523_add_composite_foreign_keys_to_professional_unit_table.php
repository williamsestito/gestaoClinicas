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
     * par (organization_id, unit_id) deve existir em units — um profissional
     * da organização A nunca pode ser vinculado a uma unidade da
     * organização B, mesmo que ambos os IDs sejam válidos isoladamente.
     */
    public function up(): void
    {
        Schema::table('professional_unit', function (Blueprint $table) {
            $table->foreign(['organization_id', 'professional_id'], 'pu_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'unit_id'], 'pu_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('professional_unit', function (Blueprint $table) {
            $table->dropForeign('pu_org_professional_fk');
            $table->dropForeign('pu_org_unit_fk');
        });
    }
};
