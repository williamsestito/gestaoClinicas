<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporte para foreign key composta a partir de professional_specialty/
     * professional_unit/professional_service/professional_registrations —
     * ver migration da tabela units.
     */
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
