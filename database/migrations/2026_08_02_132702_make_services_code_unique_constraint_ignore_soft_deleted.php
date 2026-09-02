<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mesma correção aplicada a specialties: sem isso, o código de um
     * serviço excluído logicamente nunca poderia ser reutilizado, e a
     * restauração nunca encontraria um conflito real de código.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE services DROP CONSTRAINT services_organization_id_code_unique');

        DB::statement(
            'CREATE UNIQUE INDEX services_organization_id_code_unique
             ON services (organization_id, code)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX services_organization_id_code_unique');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_organization_id_code_unique UNIQUE (organization_id, code)');
    }
};
