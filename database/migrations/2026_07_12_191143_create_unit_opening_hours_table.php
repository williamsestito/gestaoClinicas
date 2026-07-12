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
        Schema::create('unit_opening_hours', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            // Cascade aqui e intencional: horarios sao um detalhe da unidade,
            // sem sentido isolado, e a propria unidade nunca e excluida
            // fisicamente pelos fluxos do produto.
            $table->foreignUlid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['unit_id', 'day_of_week']);
        });

        DB::statement(
            'ALTER TABLE unit_opening_hours ADD CONSTRAINT unit_opening_hours_day_of_week_range
             CHECK (day_of_week BETWEEN 0 AND 6)',
        );
        DB::statement(
            'ALTER TABLE unit_opening_hours ADD CONSTRAINT unit_opening_hours_opens_before_closes
             CHECK (opens_at < closes_at)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_opening_hours');
    }
};
