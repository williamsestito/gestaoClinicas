<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jornada semanal recorrente do profissional, sempre relacionada ao
     * vínculo profissional-unidade (professional_unit) — que já garante
     * profissional, unidade, clínica e vigência do vínculo em si. Horários
     * são hora civil local da unidade (mesmo padrão de unit_opening_hours):
     * nunca convertidos para um instante UTC fixo, pois representam uma
     * regra recorrente, não um evento datado.
     *
     * A sobreposição entre jornadas (mesma unidade ou entre unidades) é
     * verificada em nível de aplicação (App\Support\Availability\WorkingHourOverlapGuard),
     * o mesmo padrão já usado para unit_opening_hours — não há EXCLUDE
     * constraint do PostgreSQL aqui (exigiria a extensão btree_gist, não
     * instalada/aprovada neste projeto). O índice único parcial abaixo é
     * apenas defesa contra duplicidade exata em concorrência.
     */
    public function up(): void
    {
        Schema::create('professional_working_hours', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_unit_id')->constrained('professional_unit')->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_unit_id', 'weekday']);
            $table->index(['professional_unit_id', 'status']);

            $table->foreign(['organization_id', 'professional_unit_id'], 'pwh_org_professional_unit_fk')
                ->references(['organization_id', 'id'])->on('professional_unit')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE professional_working_hours ADD CONSTRAINT professional_working_hours_weekday_range
             CHECK (weekday BETWEEN 0 AND 6)',
        );
        DB::statement(
            'ALTER TABLE professional_working_hours ADD CONSTRAINT professional_working_hours_starts_before_ends
             CHECK (starts_at < ends_at)',
        );
        DB::statement(
            'ALTER TABLE professional_working_hours ADD CONSTRAINT professional_working_hours_effective_range
             CHECK (effective_from IS NULL OR effective_until IS NULL OR effective_until >= effective_from)',
        );
        DB::statement(
            'ALTER TABLE professional_working_hours ADD CONSTRAINT professional_working_hours_status_valid
             CHECK (status IN (\'active\', \'inactive\'))',
        );

        DB::statement(
            'CREATE UNIQUE INDEX professional_working_hours_unique_active_interval
             ON professional_working_hours (professional_unit_id, weekday, starts_at, ends_at)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_working_hours');
    }
};
