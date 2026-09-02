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
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->softDeletes();
        });

        // A entidade legal excluída logicamente deixa de contar para a regra
        // de "uma principal por organização" — sem isso, restaurar ou marcar
        // uma nova entidade como principal ficaria bloqueado indevidamente.
        DB::statement('DROP INDEX legal_entities_one_primary_per_org');
        DB::statement(
            'CREATE UNIQUE INDEX legal_entities_one_primary_per_org
             ON legal_entities (organization_id)
             WHERE is_primary = true AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX legal_entities_one_primary_per_org');
        DB::statement(
            'CREATE UNIQUE INDEX legal_entities_one_primary_per_org
             ON legal_entities (organization_id)
             WHERE is_primary = true',
        );

        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
