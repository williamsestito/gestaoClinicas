<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A FK simples de professional_id (migration anterior) não impedia
     * `organization_id` e `professional_id` apontarem para organizações
     * diferentes — o formulário público validava a existência do
     * profissional sem escopo de organização. Troca pela mesma FK composta
     * já usada em `professional_dashboard_reminders`: com colunas nullable,
     * só é verificada quando ambas estão preenchidas (MATCH SIMPLE do
     * Postgres), então continua aceitando lead sem profissional.
     *
     * `restrictOnDelete()`, não `nullOnDelete()` como a FK simples anterior:
     * numa FK composta que inclui `organization_id`, um `ON DELETE SET
     * NULL` do Postgres zera todas as colunas da FK — incluindo
     * `organization_id`, corrompendo o isolamento multiempresa do
     * histórico. Sem impacto prático: profissional é sempre soft delete
     * (nunca exclusão física), mesma justificativa já usada em
     * `professional_dashboard_reminders`.
     */
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->dropForeign(['professional_id']);

            $table->foreign(['organization_id', 'professional_id'], 'appointment_requests_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->dropForeign('appointment_requests_org_professional_fk');

            $table->foreign('professional_id')->references('id')->on('professionals')->nullOnDelete();
        });
    }
};
