<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapa 5 (Comercial) — registro do que foi vendido, a que preço e com
     * que desconto. Não rastreia cobrança/parcela/recebimento (Etapa 6,
     * Financeiro) nem passa por caixa (também Etapa 6) — ver
     * docs/modules/sales.md. Sem soft delete: cancelamento é status, nunca
     * exclusão (RN-009/017).
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignUlid('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUlid('professional_id')->nullable()->constrained('professionals')->restrictOnDelete();
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('discount_total_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'patient_id']);
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'unit_id'], 'sales_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['organization_id', 'legal_entity_id'], 'sales_org_legal_entity_fk')
                ->references(['organization_id', 'id'])->on('legal_entities')->restrictOnDelete();
            $table->foreign(['organization_id', 'patient_id'], 'sales_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')->restrictOnDelete();
            $table->foreign(['organization_id', 'professional_id'], 'sales_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')->restrictOnDelete();
            $table->foreign(['organization_id', 'appointment_id'], 'sales_org_appointment_fk')
                ->references(['organization_id', 'id'])->on('appointments')->restrictOnDelete();
        });

        DB::statement(
            "ALTER TABLE sales ADD CONSTRAINT sales_status_valid
             CHECK (status IN ('draft', 'pending_approval', 'confirmed', 'cancelled'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
