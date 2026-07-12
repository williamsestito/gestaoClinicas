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
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('type');
            $table->string('document', 14)->unique();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('state_registration')->nullable();
            $table->string('municipal_registration')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
        });

        // Garante uma unica entidade legal principal por organizacao
        // (indice unico parcial, so PostgreSQL, sem equivalente fluente no Blueprint).
        DB::statement(
            'CREATE UNIQUE INDEX legal_entities_one_primary_per_org
             ON legal_entities (organization_id)
             WHERE is_primary = true',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entities');
    }
};
