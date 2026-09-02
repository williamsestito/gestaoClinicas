<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Support\Documents\Document;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Diferente de Specialty/Service/Professional (listagem inteira sem
 * paginação, filtrada no cliente), a listagem de pacientes é paginada e
 * filtrada no servidor — volume de pacientes tende a crescer bem além do de
 * profissionais (ver docs/roadmap.md, Etapa 2).
 */
final class PatientListQuery
{
    /** @return LengthAwarePaginator<int, Patient> */
    public function forOrganization(
        Organization $organization,
        ?string $search,
        ?RecordStatus $status,
        int $perPage = 20,
        ?string $primaryProfessionalId = null,
    ): LengthAwarePaginator {
        $query = $organization->patients()->withTrashed()->orderBy('name');

        // "Meus pacientes" não é só quem tem `primary_professional_id`
        // apontando para o profissional — inclui também quem ele já
        // atendeu de verdade (Appointment) ou tem um pré-agendamento
        // pendente com ele, mesmo sem nunca ter sido formalmente
        // designado como "principal" (achado em uso real: um profissional
        // com vários atendimentos reais via agenda via a lista vazia).
        if ($primaryProfessionalId !== null) {
            $query->where(function ($inner) use ($primaryProfessionalId) {
                $inner->where('primary_professional_id', $primaryProfessionalId)
                    ->orWhereHas('appointments', fn ($q) => $q->where('professional_id', $primaryProfessionalId))
                    ->orWhereHas('appointmentRequests', fn ($q) => $q->where('professional_id', $primaryProfessionalId));
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== null && $search !== '') {
            $digits = Document::onlyDigits($search);

            $query->where(function ($inner) use ($search, $digits) {
                $inner->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");

                if ($digits !== '') {
                    $inner->orWhere('document', 'like', "%{$digits}%");
                }
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
