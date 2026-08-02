<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ProfessionalOperationalStatus;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Consulta dedicada da listagem de profissionais — resolve, em poucas
 * consultas (eager loading + withCount, sem N+1), tudo que os filtros da
 * tela precisam: unidades/especialidades ativas, contagem de serviços,
 * jornada configurada, ausência em andamento e situação operacional
 * resumida (calculada a partir dos mesmos agregados, sem repetir consultas
 * por profissional).
 */
final class ProfessionalListQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function forOrganization(Organization $organization): Collection
    {
        $now = Carbon::now();

        return $organization->professionals()
            ->withTrashed()
            ->with([
                'user:id,name',
                'unitLinks' => fn ($query) => $query->where('status', RecordStatus::Active)->with('unit:id,name'),
                'specialtyLinks' => fn ($query) => $query->where('status', RecordStatus::Active)->with('specialty:id,name'),
            ])
            ->withCount([
                'serviceLinks as active_services_count' => fn ($query) => $query->where('status', RecordStatus::Active),
                'workingHours as active_working_hours_count' => fn ($query) => $query->where('professional_working_hours.status', RecordStatus::Active),
                'timeBlocks as ongoing_time_blocks_count' => fn ($query) => $query
                    ->where('status', RecordStatus::Active)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>', $now),
            ])
            ->orderBy('display_name')
            ->get()
            ->map(fn (Professional $professional) => $this->mapRow($professional));
    }

    /** @return array<string, mixed> */
    private function mapRow(Professional $professional): array
    {
        $hasActiveUnit = $professional->unitLinks->isNotEmpty();
        $hasActiveSpecialty = $professional->specialtyLinks->isNotEmpty();
        $hasActiveService = (int) $professional->getAttribute('active_services_count') > 0;
        $hasWorkingHours = (int) $professional->getAttribute('active_working_hours_count') > 0;
        $hasOngoingAbsence = (int) $professional->getAttribute('ongoing_time_blocks_count') > 0;

        $operationalStatus = $this->resolveOperationalStatus($professional, $hasActiveUnit, $hasWorkingHours);

        return [
            'id' => $professional->id,
            'display_name' => $professional->display_name,
            'email' => $professional->email,
            'phone' => $professional->phone,
            'document' => $professional->document,
            'photo_path' => $professional->photo_path,
            'linked_user_name' => $professional->user?->name,
            'status' => $professional->status->value,
            'deleted_at' => $professional->deleted_at,
            'updated_at' => $professional->updated_at,
            'unit_ids' => $professional->unitLinks->pluck('unit_id')->values(),
            'unit_names' => $professional->unitLinks->pluck('unit.name')->filter()->values(),
            'specialty_ids' => $professional->specialtyLinks->pluck('specialty_id')->values(),
            'specialty_names' => $professional->specialtyLinks->pluck('specialty.name')->filter()->values(),
            'active_services_count' => (int) $professional->getAttribute('active_services_count'),
            'has_active_unit' => $hasActiveUnit,
            'has_active_specialty' => $hasActiveSpecialty,
            'has_active_service' => $hasActiveService,
            'has_working_hours' => $hasWorkingHours,
            'has_ongoing_absence' => $hasOngoingAbsence,
            'operational_status' => $operationalStatus->value,
        ];
    }

    private function resolveOperationalStatus(Professional $professional, bool $hasActiveUnit, bool $hasWorkingHours): ProfessionalOperationalStatus
    {
        if ($professional->status !== RecordStatus::Active || $professional->trashed()) {
            return ProfessionalOperationalStatus::Inactive;
        }

        if (! $hasActiveUnit || ! $hasWorkingHours) {
            return ProfessionalOperationalStatus::Incomplete;
        }

        return ProfessionalOperationalStatus::Operational;
    }
}
