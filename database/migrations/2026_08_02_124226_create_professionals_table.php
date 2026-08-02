<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            // users.id e auto-increment (nao ULID) — vinculo opcional, nunca concede
            // permissoes por si so (ver App\Support\Authorization\PermissionChecker).
            // A compatibilidade do usuario vinculado com a organizacao (possuir
            // OrganizationMembership ativo nela) e validada em Action, nao no banco.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('social_name')->nullable();
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Sem mascara no banco (mesmo padrao de legal_entities.document);
            // mascarado somente na apresentacao (App\Support\Documents\Document).
            $table->string('document')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index('user_id');
            $table->unique(['organization_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
