<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalDashboardReminder;

/**
 * Sem auditoria de propósito: um lembrete tipo post-it é conteúdo pessoal
 * do profissional no próprio dashboard, sem relevância de negócio (não
 * envolve paciente, financeiro ou dado clínico) — diferente de todo o
 * restante das Actions deste diretório.
 */
class CreateProfessionalDashboardReminderAction
{
    /** @param array<string, mixed> $attributes */
    public function handle(Organization $organization, Professional $professional, array $attributes): ProfessionalDashboardReminder
    {
        return ProfessionalDashboardReminder::query()->create([
            'organization_id' => $organization->id,
            'professional_id' => $professional->id,
            'body' => $attributes['body'],
            'color' => $attributes['color'],
        ]);
    }
}
