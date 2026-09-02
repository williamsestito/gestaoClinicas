<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo opcional entre o conteúdo promocional (`site_professionals`/
     * `site_services`, singleton — sem `organization_id`, ver ADR-010) e o
     * cadastro operacional multiempresa (`professionals`/`services`, com
     * `organization_id`). Como as tabelas de site não têm `organization_id`,
     * uma FK composta multiempresa não é expressável aqui: o bloqueio
     * cross-tenant acontece no domínio (Actions revalidam que o registro
     * operacional pertence à organização ativa antes de vincular), não por
     * constraint de banco — documentado em docs/modules/public-integration.md.
     * Nenhum preenchimento automático: a coluna nasce nula para todo
     * registro promocional existente, preservando-o.
     */
    public function up(): void
    {
        Schema::table('site_professionals', function (Blueprint $table) {
            $table->ulid('professional_id')->nullable()->after('id');
            $table->foreign('professional_id')->references('id')->on('professionals')->nullOnDelete();
        });

        Schema::table('site_services', function (Blueprint $table) {
            $table->ulid('service_id')->nullable()->after('id');
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_professionals', function (Blueprint $table) {
            $table->dropForeign(['professional_id']);
            $table->dropColumn('professional_id');
        });

        Schema::table('site_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });
    }
};
