<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\RecordStatus;
use App\Models\ProfessionalService;
use Illuminate\Validation\ValidationException;

/**
 * Resolve o vínculo profissional/serviço ativo e compatível com a unidade
 * informada — usado tanto pela criação de agendamento do staff
 * (App\Http\Controllers\Organization\AppointmentController) quanto pelo
 * booking do portal do paciente
 * (App\Http\Controllers\PatientPortal\PatientAppointmentController), para
 * não duplicar a validação nos dois lugares.
 */
final class ActiveProfessionalServiceLinkResolver
{
    public function resolve(string $organizationId, string $professionalId, string $serviceId, string $unitId): ProfessionalService
    {
        $link = ProfessionalService::query()
            ->where('organization_id', $organizationId)
            ->where('professional_id', $professionalId)
            ->where('service_id', $serviceId)
            ->where('status', RecordStatus::Active)
            ->first();

        if (! $link || ! $link->compatibleUnitIds()->contains($unitId)) {
            throw ValidationException::withMessages([
                'service_id' => 'Este profissional não executa o serviço selecionado nesta unidade.',
            ]);
        }

        return $link;
    }
}
