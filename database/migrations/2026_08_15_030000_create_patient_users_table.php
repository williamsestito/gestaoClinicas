<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conta de autenticação do paciente/responsável (guard "patient"),
        // separada de "users" (staff) — nunca a mesma tabela, ver
        // docs/modules/patient-portal.md. Sem soft delete: é conta de acesso,
        // nao registro de negocio (mesma semantica de "users").
        Schema::create('patient_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Necessario para a FK composta de patient_user_links (mesmo
            // padrao de patients/professional_unit).
            $table->unique(['organization_id', 'id']);
            // Escopado por organizacao (mesmo padrao de patients.document),
            // funcionalmente igual a unico global nesta instalacao
            // single-tenant (ver docs/decisions/ADR-010).
            $table->unique(['organization_id', 'email']);
            $table->index(['organization_id', 'is_active']);
        });

        // Separada da tabela de staff (password_reset_tokens) para nunca
        // compartilhar broker entre os dois guards.
        Schema::create('patient_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_password_reset_tokens');
        Schema::dropIfExists('patient_users');
    }
};
