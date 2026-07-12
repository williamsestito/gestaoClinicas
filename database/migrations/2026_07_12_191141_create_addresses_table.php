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
        Schema::create('addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('addressable_type');
            $table->ulid('addressable_id');
            $table->string('postal_code', 8);
            $table->string('street');
            $table->string('number');
            $table->string('complement')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->string('state', 2);
            $table->string('country', 2)->default('BR');
            $table->timestamps();

            $table->index(['addressable_type', 'addressable_id']);
            $table->index('organization_id');
            $table->index('postal_code');
        });

        // CEP so numeros (8 digitos) e UF em maiusculas de 2 letras -
        // a lista de UFs validas (27) fica a cargo da validacao de aplicacao.
        DB::statement(
            "ALTER TABLE addresses ADD CONSTRAINT addresses_postal_code_digits_only
             CHECK (postal_code ~ '^[0-9]{8}$')",
        );
        DB::statement(
            "ALTER TABLE addresses ADD CONSTRAINT addresses_state_format
             CHECK (state ~ '^[A-Z]{2}$')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
