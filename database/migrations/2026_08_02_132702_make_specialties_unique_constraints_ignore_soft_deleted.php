<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correção da fundação da Etapa 2.1: as constraints únicas de
     * `specialties.name`/`specialties.code` eram simples (incluíam
     * registros excluídos logicamente), o que tornava impossível reutilizar
     * o nome/código de uma especialidade após excluí-la — e também
     * impossível o cenário de conflito na restauração exigido pela Etapa
     * 2.2 ("já existe um registro ativo com os mesmos dados"). Convertidas
     * para índices únicos parciais (ignoram `deleted_at`), no mesmo padrão
     * já usado em professional_specialty/professional_unit desde a
     * Etapa 2.1.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE specialties DROP CONSTRAINT specialties_organization_id_name_unique');
        DB::statement('ALTER TABLE specialties DROP CONSTRAINT specialties_organization_id_code_unique');

        DB::statement(
            'CREATE UNIQUE INDEX specialties_organization_id_name_unique
             ON specialties (organization_id, name)
             WHERE deleted_at IS NULL',
        );

        DB::statement(
            'CREATE UNIQUE INDEX specialties_organization_id_code_unique
             ON specialties (organization_id, code)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX specialties_organization_id_name_unique');
        DB::statement('DROP INDEX specialties_organization_id_code_unique');

        DB::statement('ALTER TABLE specialties ADD CONSTRAINT specialties_organization_id_name_unique UNIQUE (organization_id, name)');
        DB::statement('ALTER TABLE specialties ADD CONSTRAINT specialties_organization_id_code_unique UNIQUE (organization_id, code)');
    }
};
