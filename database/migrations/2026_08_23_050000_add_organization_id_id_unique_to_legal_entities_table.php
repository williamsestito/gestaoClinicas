<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Suporte para foreign key composta a partir de sales — ver migration da tabela units. */
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
