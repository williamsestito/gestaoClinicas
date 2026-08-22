<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\ProfessionalDashboardReminder;

class DeleteProfessionalDashboardReminderAction
{
    public function handle(ProfessionalDashboardReminder $reminder): void
    {
        $reminder->delete();
    }
}
