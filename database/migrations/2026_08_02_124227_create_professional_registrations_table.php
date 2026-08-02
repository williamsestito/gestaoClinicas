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
        Schema::create('professional_registrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // organization_id redundante (tambem alcancavel via professional_id) —
            // mantido para permitir consultas/constraints com escopo direto de
            // organizacao, sem depender de join, e para bloquear cruzamento entre
            // clinicas mesmo que um professional_id de outra organizacao seja informado.
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->cascadeOnDelete();
            // Conselho de classe (ex.: CRM, CRO, COREN, CREFITO, CRP) mantido como
            // texto livre — o catalogo varia por tipo de profissional e um enum
            // fechado ficaria frageis/desatualizado (decisao registrada na Etapa 2.0).
            $table->string('council');
            $table->string('registration_type')->nullable();
            $table->string('registration_number');
            $table->string('state', 2);
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_primary')->default(false);
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'status']);
            $table->unique(['organization_id', 'council', 'registration_number', 'state']);
        });

        DB::statement(
            'ALTER TABLE professional_registrations ADD CONSTRAINT professional_registrations_expiry_after_issue
             CHECK (expires_at IS NULL OR issued_at IS NULL OR expires_at >= issued_at)',
        );

        // Garante no maximo um registro principal ativo (nao excluido) por profissional.
        DB::statement(
            'CREATE UNIQUE INDEX professional_registrations_one_primary_per_professional
             ON professional_registrations (professional_id)
             WHERE is_primary = true AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_registrations');
    }
};
