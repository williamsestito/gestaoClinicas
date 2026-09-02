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
        Schema::table('units', function (Blueprint $table) {
            $table->softDeletes();
        });

        // A unidade matriz excluída logicamente deixa de contar para a regra
        // de "uma matriz por organização" — sem isso, designar uma nova
        // matriz após excluir a anterior ficaria bloqueado indevidamente.
        DB::statement('DROP INDEX units_one_headquarters_per_org');
        DB::statement(
            'CREATE UNIQUE INDEX units_one_headquarters_per_org
             ON units (organization_id)
             WHERE is_headquarters = true AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX units_one_headquarters_per_org');
        DB::statement(
            'CREATE UNIQUE INDEX units_one_headquarters_per_org
             ON units (organization_id)
             WHERE is_headquarters = true',
        );

        Schema::table('units', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
