<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloqueia no banco (não só em validação futura de Action/frontend) que
     * um registro profissional aponte para um profissional de outra
     * organização: a FK composta só é satisfeita quando o par
     * (organization_id, professional_id) existir em professionals com o
     * mesmo organization_id da própria linha.
     */
    public function up(): void
    {
        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->foreign(['organization_id', 'professional_id'], 'pr_registrations_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->dropForeign('pr_registrations_org_professional_fk');
        });
    }
};
