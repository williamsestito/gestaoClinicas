<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lembretes tipo post-it no dashboard do profissional — conteúdo
     * pessoal/efêmero, não um registro de negócio (não há paciente,
     * financeiro ou dado clínico envolvido), por isso pode ser excluído
     * fisicamente (ver DeleteProfessionalDashboardReminderAction), ao
     * contrário do restante dos dados da clínica.
     */
    public function up(): void
    {
        Schema::create('professional_dashboard_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->text('body');
            $table->string('color')->default('yellow');
            $table->timestamps();

            $table->index(['organization_id', 'professional_id']);

            $table->foreign(['organization_id', 'professional_id'], 'reminders_org_professional_fk')
                ->references(['organization_id', 'id'])->on('professionals')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_dashboard_reminders');
    }
};
