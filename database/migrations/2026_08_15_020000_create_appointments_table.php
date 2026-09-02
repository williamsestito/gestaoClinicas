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
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            // Instante real em UTC (evento datado) — mesmo padrão de
            // professional_time_blocks, nunca hora civil recorrente.
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('requested');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'starts_at']);
            $table->index(['patient_id']);
            $table->index(['organization_id', 'status']);

            $table->foreign(['organization_id', 'unit_id'], 'appointments_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'professional_id'], 'appointments_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'patient_id'], 'appointments_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'service_id'], 'appointments_org_service_fk')
                ->references(['organization_id', 'id'])->on('services')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE appointments ADD CONSTRAINT appointments_ends_after_starts
             CHECK (ends_at > starts_at)',
        );
        DB::statement(
            'ALTER TABLE appointments ADD CONSTRAINT appointments_status_valid
             CHECK (status IN (\'requested\', \'awaiting_confirmation\', \'confirmed\', \'checked_in\', \'in_progress\', \'completed\', \'cancelled\', \'no_show\'))',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
