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
        Schema::create('patients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('preferred_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUlid('primary_professional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->string('name');
            $table->string('preferred_name')->nullable();
            // Sem mascara no banco (mesmo padrao de professionals.document);
            // mascarado somente na apresentacao (App\Support\Documents\Document).
            $table->string('document')->nullable();
            $table->date('birth_date');
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('origin')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            // Necessario para as FKs compostas de patient_responsibles e
            // patient_emergency_contacts (mesmo padrao de professional_unit).
            $table->unique(['organization_id', 'id']);
        });

        // Documento unico apenas entre registros ativos (nao soft-deleted) —
        // mesmo padrao de specialties, permite reuso do CPF apos exclusao logica.
        DB::statement(
            'CREATE UNIQUE INDEX patients_unique_active_document
             ON patients (organization_id, document)
             WHERE deleted_at IS NULL AND document IS NOT NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
