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
        Schema::create('professional_service', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            // Valores especificos opcionais — quando ausentes, o sistema usa os
            // valores padrao do servico (default_duration_minutes/default_price_cents/
            // buffer_before_minutes/buffer_after_minutes). Sem agenda/disponibilidade
            // nesta etapa, apenas o vinculo e os overrides opcionais.
            $table->unsignedSmallInteger('custom_duration_minutes')->nullable();
            $table->unsignedInteger('custom_price_cents')->nullable();
            $table->unsignedSmallInteger('custom_buffer_before_minutes')->nullable();
            $table->unsignedSmallInteger('custom_buffer_after_minutes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'status']);
            $table->index(['service_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE professional_service ADD CONSTRAINT professional_service_duration_within_range
             CHECK (custom_duration_minutes IS NULL OR (custom_duration_minutes > 0 AND custom_duration_minutes <= 1440))',
        );

        DB::statement(
            'CREATE UNIQUE INDEX professional_service_unique_active_link
             ON professional_service (professional_id, service_id)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_service');
    }
};
