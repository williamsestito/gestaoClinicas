<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista de espera (Etapa 3.3 do roadmap) — registro manual que a
     * recepção consulta e converte à mão, mesmo padrão de "conversão
     * assistida" já usado para App\Models\AppointmentRequest (Etapa 3.2).
     * Sem motor de notificação automática nesta etapa. `professional_id`
     * nulo é uma opção válida ("qualquer profissional disponível").
     */
    public function up(): void
    {
        Schema::create('appointment_waitlist_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignUlid('professional_id')->nullable()->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->date('preferred_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('waiting');
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'unit_id', 'status']);

            $table->foreign(['organization_id', 'unit_id'], 'waitlist_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'professional_id'], 'waitlist_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'service_id'], 'waitlist_org_service_fk')
                ->references(['organization_id', 'id'])->on('services')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'patient_id'], 'waitlist_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_waitlist_entries');
    }
};
