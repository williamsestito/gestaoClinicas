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
     * par (organization_id, specialty_id) deve existir em specialties — um
     * profissional da organização A nunca pode ser vinculado a uma
     * especialidade da organização B, mesmo que ambos os IDs sejam válidos
     * isoladamente.
     */
    public function up(): void
    {
        Schema::table('professional_specialty', function (Blueprint $table) {
            $table->foreign(['organization_id', 'professional_id'], 'ps_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'specialty_id'], 'ps_org_specialty_fk')
                ->references(['organization_id', 'id'])->on('specialties')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('professional_specialty', function (Blueprint $table) {
            $table->dropForeign('ps_org_professional_fk');
            $table->dropForeign('ps_org_specialty_fk');
        });
    }
};
