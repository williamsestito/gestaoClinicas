<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arquivos clínicos anexados a um prontuário (Seção 11 do documento de
     * visão) — só as categorias clinicamente sensíveis entram nesta etapa
     * (exames, fotografias clínicas, prescrições, atestados/declarações,
     * consentimentos, encaminhamentos, laudos); categorias puramente
     * administrativas (contratos, documentos pessoais, comprovantes) ficam
     * fora de escopo. Visualização/download são auditados explicitamente
     * pelo controller (RN-008), nunca aqui.
     */
    public function up(): void
    {
        Schema::create('medical_record_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignUlid('medical_record_id')->constrained('medical_records')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('category');
            $table->string('original_filename');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['organization_id', 'medical_record_id']);

            $table->foreign(['organization_id', 'unit_id'], 'medical_record_files_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'medical_record_id'], 'medical_record_files_org_record_fk')
                ->references(['organization_id', 'id'])->on('medical_records')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_files');
    }
};
