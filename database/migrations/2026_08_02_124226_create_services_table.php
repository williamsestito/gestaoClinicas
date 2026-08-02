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
        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_duration_minutes');
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('default_price_cents')->nullable();
            $table->string('status')->default('active');
            $table->string('color')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('requires_manual_confirmation')->default(false);
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        // Duracao sempre positiva e dentro de um limite coerente (ate 24h);
        // intervalos de buffer nunca negativos (ja garantido pelo tipo
        // unsigned, o check e redundante mas explicito na intencao do dominio).
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_duration_within_range
             CHECK (default_duration_minutes > 0 AND default_duration_minutes <= 1440)',
        );
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_buffers_within_range
             CHECK (buffer_before_minutes <= 1440 AND buffer_after_minutes <= 1440)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
