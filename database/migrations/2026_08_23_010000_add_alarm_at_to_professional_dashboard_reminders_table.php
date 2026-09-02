<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horário opcional (UTC) em que o post-it deve virar um alerta na tela
     * do dashboard (ex.: "tomar remédio às 12h") — checado só no cliente,
     * enquanto o dashboard estiver aberto (sem push/Service Worker nesta
     * fase). Silenciar o alarme (ver DismissProfessionalDashboardReminderAlarmAction)
     * apenas zera esta coluna; o post-it em si continua existindo.
     */
    public function up(): void
    {
        Schema::table('professional_dashboard_reminders', function (Blueprint $table) {
            $table->timestamp('alarm_at')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('professional_dashboard_reminders', function (Blueprint $table) {
            $table->dropColumn('alarm_at');
        });
    }
};
