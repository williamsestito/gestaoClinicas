<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solicitação de agendamento enviada pela landing pública (lead).
        // Não é um agendamento confirmado — a clínica entra em contato e
        // confirma manualmente (não existe agenda/disponibilidade real
        // ainda, ver App\Enums\AppointmentRequestStatus).
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignUlid('unit_id')->nullable()
                ->constrained('units')->nullOnDelete();
            $table->foreignId('service_id')->nullable()
                ->constrained('site_services')->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('preferred_period')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('terms_accepted_at');
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
