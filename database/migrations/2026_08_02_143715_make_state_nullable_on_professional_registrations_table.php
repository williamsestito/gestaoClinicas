<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajuste no contrato da Etapa 2.1: a Etapa 2.5 exige explicitamente não
     * obrigar UF para conselho/órgão nacional ou internacional, mas a
     * coluna havia sido criada como NOT NULL. Registros já existentes
     * mantêm seu valor — a mudança é apenas relaxar a obrigatoriedade.
     * Usa SQL bruto (em vez de Blueprint::change(), que exigiria adicionar
     * a dependência doctrine/dbal) para permanecer consistente com o
     * restante das migrations deste domínio.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE professional_registrations ALTER COLUMN state DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE professional_registrations ALTER COLUMN state SET NOT NULL');
    }
};
