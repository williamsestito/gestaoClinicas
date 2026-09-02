<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapa 5 (Comercial) — catálogo de produtos vendáveis. Sem campos de
     * estoque/lote/validade: só passam a existir quando o módulo de
     * Estoque (Etapa 7) chegar de verdade.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('barcode')->nullable();
            $table->string('unit_of_measure')->default('un');
            $table->unsignedInteger('cost_cents')->nullable();
            $table->unsignedSmallInteger('margin_percentage')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedSmallInteger('max_discount_percentage')->nullable();
            $table->string('status')->default('active');
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->unique(['organization_id', 'id']);
        });

        // Mesmo padrão de services: código reutilizável após exclusão logica.
        DB::statement(
            'CREATE UNIQUE INDEX products_organization_id_code_unique
             ON products (organization_id, code)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
