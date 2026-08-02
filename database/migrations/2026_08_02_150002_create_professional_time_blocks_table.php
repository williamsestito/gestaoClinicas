<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ausências, folgas e bloqueios (exceções datadas à jornada regular).
     * `starts_at`/`ends_at` são instantes reais (UTC) — diferente de
     * professional_working_hours, que guarda hora civil recorrente. Escopo
     * explícito via `scope` + `unit_id` (nunca `unit_id = null` com
     * significado implícito): `all_units` sempre tem `unit_id` nulo,
     * `specific_unit` sempre tem `unit_id` preenchido — garantido por CHECK.
     */
    public function up(): void
    {
        Schema::create('professional_time_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('type');
            $table->string('scope');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_all_day')->default(false);
            $table->string('reason', 255)->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'status']);
            $table->index(['professional_id', 'starts_at', 'ends_at']);
            $table->index('unit_id');

            $table->foreign(['organization_id', 'professional_id'], 'ptb_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'unit_id'], 'ptb_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE professional_time_blocks ADD CONSTRAINT professional_time_blocks_ends_after_starts
             CHECK (ends_at > starts_at)',
        );
        DB::statement(
            "ALTER TABLE professional_time_blocks ADD CONSTRAINT professional_time_blocks_scope_unit_consistency
             CHECK (
                 (scope = 'all_units' AND unit_id IS NULL)
                 OR (scope = 'specific_unit' AND unit_id IS NOT NULL)
             )",
        );
        DB::statement(
            "ALTER TABLE professional_time_blocks ADD CONSTRAINT professional_time_blocks_scope_valid
             CHECK (scope IN ('all_units', 'specific_unit'))",
        );
        DB::statement(
            "ALTER TABLE professional_time_blocks ADD CONSTRAINT professional_time_blocks_type_valid
             CHECK (type IN ('vacation', 'day_off', 'absence', 'administrative_block', 'external_event', 'partial_unavailability'))",
        );
        DB::statement(
            "ALTER TABLE professional_time_blocks ADD CONSTRAINT professional_time_blocks_status_valid
             CHECK (status IN ('active', 'inactive'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_time_blocks');
    }
};
