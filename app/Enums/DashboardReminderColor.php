<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cores disponíveis para um lembrete tipo post-it no dashboard do
 * profissional (App\Models\ProfessionalDashboardReminder) — só estética,
 * sem significado de negócio.
 */
enum DashboardReminderColor: string
{
    case Yellow = 'yellow';
    case Pink = 'pink';
    case Blue = 'blue';
    case Green = 'green';
}
