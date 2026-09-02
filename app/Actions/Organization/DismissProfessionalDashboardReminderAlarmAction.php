<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\ProfessionalDashboardReminder;

/**
 * Silencia o alarme de um lembrete — só zera `alarm_at`, o post-it em si
 * continua existindo (ver DeleteProfessionalDashboardReminderAction para a
 * exclusão completa).
 */
class DismissProfessionalDashboardReminderAlarmAction
{
    public function handle(ProfessionalDashboardReminder $reminder): void
    {
        $reminder->update(['alarm_at' => null]);
    }
}
