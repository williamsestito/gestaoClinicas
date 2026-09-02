<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            // Dígitos apenas (sem máscara), como o restante do projeto
            // normaliza CPF/CNPJ (ver App\Support\Documents\Document).
            // Não reutiliza a tabela polimórfica `addresses` (ver
            // docs/decisions/ADR-009): ela exige organization_id
            // obrigatório e addressable_id ULID, incompatíveis com um
            // endereço pessoal de usuário (users.id é bigint).
            $table->string('cpf', 11)->nullable()->unique()->after('phone');
            $table->string('photo_path')->nullable()->after('cpf');
            $table->string('address_postal_code', 8)->nullable()->after('photo_path');
            $table->string('address_street')->nullable()->after('address_postal_code');
            $table->string('address_number')->nullable()->after('address_street');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->string('address_neighborhood')->nullable()->after('address_complement');
            $table->string('address_city')->nullable()->after('address_neighborhood');
            $table->string('address_state', 2)->nullable()->after('address_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'cpf',
                'photo_path',
                'address_postal_code',
                'address_street',
                'address_number',
                'address_complement',
                'address_neighborhood',
                'address_city',
                'address_state',
            ]);
        });
    }
};
