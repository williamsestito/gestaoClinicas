<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_responsibles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Redundante em relacao a patient_id, mas necessario para a FK
            // composta abaixo bloquear no banco o cruzamento entre
            // organizacoes diferentes (mesmo padrao de professional_specialty).
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('name');
            // Sem mascara no banco, mesmo padrao de patients.document.
            $table->string('document')->nullable();
            $table->string('phone');
            $table->string('relationship');
            $table->boolean('is_legal_guardian')->default(false);
            $table->boolean('is_financial_responsible')->default(false);
            $table->boolean('is_authorized_representative')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'is_legal_guardian']);

            $table->foreign(['organization_id', 'patient_id'], 'patient_responsibles_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_responsibles');
    }
};
