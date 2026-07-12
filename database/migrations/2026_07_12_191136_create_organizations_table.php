<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->string('default_timezone')->default('America/Sao_Paulo');
            $table->string('default_currency', 3)->default('BRL');
            $table->string('locale', 10)->default('pt_BR');
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
