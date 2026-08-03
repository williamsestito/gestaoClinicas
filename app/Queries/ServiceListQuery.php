<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\RecordStatus;
use App\Enums\ServiceAvailabilityScope;
use App\Models\Organization;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Consulta dedicada da listagem de serviços — resolve especialidades,
 * unidades disponíveis e contagem de profissionais vinculados sem N+1,
 * para alimentar os filtros ampliados da tela (especialidade, unidade,
 * profissionais vinculados, exibição pública, disponibilidade
 * operacional).
 */
final class ServiceListQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function forOrganization(Organization $organization): Collection
    {
        $activeUnitIds = $organization->units()
            ->where('status', RecordStatus::Active)
            ->pluck('id');

        return $organization->services()
            ->withTrashed()
            ->with([
                'specialtyLinks:id,service_id,specialty_id',
                'specialtyLinks.specialty:id,name',
                'unitLinks:id,service_id,unit_id',
            ])
            ->withCount(['professionalLinks as active_professionals_count' => fn ($query) => $query->where('status', RecordStatus::Active)])
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => $this->mapRow($service, $activeUnitIds));
    }

    /**
     * @param  Collection<int, string>  $activeUnitIds
     * @return array<string, mixed>
     */
    private function mapRow(Service $service, Collection $activeUnitIds): array
    {
        $availableUnitIds = match ($service->unit_availability_scope) {
            ServiceAvailabilityScope::AllUnits => $activeUnitIds,
            ServiceAvailabilityScope::SelectedUnits => $service->unitLinks->pluck('unit_id')->intersect($activeUnitIds)->values(),
            ServiceAvailabilityScope::None => collect(),
        };

        return [
            'id' => $service->id,
            'name' => $service->name,
            'code' => $service->code,
            'default_duration_minutes' => $service->default_duration_minutes,
            'default_price_cents' => $service->default_price_cents,
            'status' => $service->status->value,
            'is_public' => $service->is_public,
            'unit_availability_scope' => $service->unit_availability_scope->value,
            'specialty_ids' => $service->specialtyLinks->pluck('specialty_id')->values(),
            'specialties' => $service->specialtyLinks->pluck('specialty.name')->filter()->values(),
            'unit_ids' => $availableUnitIds->values(),
            'has_available_unit' => $availableUnitIds->isNotEmpty(),
            'professionals_count' => (int) $service->getAttribute('active_professionals_count'),
            'deleted_at' => $service->deleted_at,
            'updated_at' => $service->updated_at,
        ];
    }
}
