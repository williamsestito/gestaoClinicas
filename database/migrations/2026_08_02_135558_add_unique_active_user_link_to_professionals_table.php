<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Decisão de cardinalidade da Etapa 2.4: um usuário pode estar vinculado
     * a no máximo um profissional ativo (não excluído) por organização —
     * evita que o mesmo usuário represente dois cadastros de profissional
     * diferentes na mesma clínica. Índice parcial: ignora `user_id` nulo
     * (vínculo é opcional) e registros excluídos logicamente.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX professionals_one_active_per_user_per_org
             ON professionals (organization_id, user_id)
             WHERE user_id IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX professionals_one_active_per_user_per_org');
    }
};
