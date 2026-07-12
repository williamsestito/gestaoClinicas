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
        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('slug');
            $table->string('status')->default('active');
            $table->boolean('is_headquarters')->default(false);
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'slug']);
            $table->index('status');
            $table->index('legal_entity_id');
        });

        // Garante uma unica unidade matriz por organizacao (indice unico parcial).
        DB::statement(
            'CREATE UNIQUE INDEX units_one_headquarters_per_org
             ON units (organization_id)
             WHERE is_headquarters = true',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
