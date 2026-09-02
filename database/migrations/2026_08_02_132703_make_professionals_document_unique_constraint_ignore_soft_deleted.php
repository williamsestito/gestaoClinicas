<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mesma correção aplicada a specialties/services: sem isso, o documento
     * de um profissional excluído logicamente nunca poderia ser reutilizado
     * por um novo cadastro, e a restauração nunca encontraria um conflito
     * real de documento.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE professionals DROP CONSTRAINT professionals_organization_id_document_unique');

        DB::statement(
            'CREATE UNIQUE INDEX professionals_organization_id_document_unique
             ON professionals (organization_id, document)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX professionals_organization_id_document_unique');
        DB::statement('ALTER TABLE professionals ADD CONSTRAINT professionals_organization_id_document_unique UNIQUE (organization_id, document)');
    }
};
