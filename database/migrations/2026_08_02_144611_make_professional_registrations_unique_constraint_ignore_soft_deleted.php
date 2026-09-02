<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mesma correção já aplicada a specialties/services/professionals: a
     * constraint única de (organization_id, council, registration_number,
     * state) era simples (incluía registros excluídos logicamente),
     * impedindo reutilizar um registro após excluí-lo e impossibilitando o
     * cenário de conflito na restauração exigido pela Etapa 2.5.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE professional_registrations DROP CONSTRAINT professional_registrations_organization_id_council_registration');

        DB::statement(
            'CREATE UNIQUE INDEX professional_registrations_org_council_number_state_unique
             ON professional_registrations (organization_id, council, registration_number, state)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX professional_registrations_org_council_number_state_unique');

        DB::statement(
            'ALTER TABLE professional_registrations ADD CONSTRAINT professional_registrations_organization_id_council_registration
             UNIQUE (organization_id, council, registration_number, state)',
        );
    }
};
