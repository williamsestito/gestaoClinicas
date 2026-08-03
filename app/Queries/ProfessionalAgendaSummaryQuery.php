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
 * Consulta somente-leitura para a tela administrativa "Agendas" — resume,
 * por profissional, os dias da semana com jornada configurada, a vigência
 * efetiva (menor início / maior fim entre os intervalos ativos) e a
 * quantidade de ausências/bloqueios em andamento. Sem N+1: uma única
 * consulta de jornada eager-carregada, agregada em memória por
 * profissional (a quantidade de intervalos por profissional é sempre
 * pequena).
 */
final class ProfessionalAgendaSummaryQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function forOrganization(Organization $organization): Collection
    {
        $now = Carbon::now();

        return $organization->professionals()
            ->where('status', RecordStatus::Active)
            ->with([
                'unitLinks' => fn ($query) => $query->where('status', RecordStatus::Active)->with('unit:id,name'),
                'specialtyLinks' => fn ($query) => $query->where('status', RecordStatus::Active)->with('specialty:id,name'),
                'serviceLinks' => fn ($query) => $query->where('status', RecordStatus::Active),
                'primarySpecialtyLink.specialty:id,name',
                'workingHours' => fn ($query) => $query->where('professional_working_hours.status', RecordStatus::Active),
            ])
            ->withCount([
                'serviceLinks as active_services_count' => fn ($query) => $query->where('status', RecordStatus::Active),
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
        $workingHours = $professional->workingHours;
        $hasActiveUnit = $professional->unitLinks->isNotEmpty();
        $hasWorkingHours = $workingHours->isNotEmpty();
        $hasOngoingAbsence = (int) $professional->getAttribute('ongoing_time_blocks_count') > 0;

        $weekdays = $workingHours->pluck('weekday')->unique()->sort()->values();
        $effectiveFromDates = $workingHours->pluck('effective_from')->filter();
        $effectiveUntilDates = $workingHours->pluck('effective_until');

        $operationalStatus = match (true) {
            $professional->status !== RecordStatus::Active => ProfessionalOperationalStatus::Inactive,
            ! $hasActiveUnit || ! $hasWorkingHours => ProfessionalOperationalStatus::Incomplete,
            default => ProfessionalOperationalStatus::Operational,
        };

        return [
            'id' => $professional->id,
            'display_name' => $professional->display_name,
            'status' => $professional->status->value,
            'operational_status' => $operationalStatus->value,
            'primary_specialty_name' => $professional->primarySpecialtyLink?->specialty?->name,
            'unit_ids' => $professional->unitLinks->pluck('unit_id')->values(),
            'unit_names' => $professional->unitLinks->pluck('unit.name')->filter()->values(),
            'specialty_ids' => $professional->specialtyLinks->pluck('specialty_id')->values(),
            'service_ids' => $professional->serviceLinks->pluck('service_id')->values(),
            'weekdays' => $weekdays,
            'vigency_from' => $effectiveFromDates->isEmpty() ? null : $effectiveFromDates->min()?->format('Y-m-d'),
            'vigency_until' => $effectiveUntilDates->contains(null) ? null : $effectiveUntilDates->filter()->max()?->format('Y-m-d'),
            'working_hours_count' => $workingHours->count(),
            'has_working_hours' => $hasWorkingHours,
            'ongoing_time_blocks_count' => (int) $professional->getAttribute('ongoing_time_blocks_count'),
            'has_ongoing_absence' => $hasOngoingAbsence,
            'has_conflict_alert' => $hasActiveUnit && ! $hasWorkingHours,
        ];
    }
}
