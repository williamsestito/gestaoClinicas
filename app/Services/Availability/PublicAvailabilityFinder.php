<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolve, passo a passo e somente leitura, a cadeia unidade →
 * especialidade → serviço → profissional → datas → horários usada pela
 * busca pública de disponibilidade da landing. Nunca persiste nada, nunca
 * expõe dado sensível — cada método retorna arrays já normalizados.
 *
 * Quando o paciente escolhe "qualquer profissional", a busca de datas e
 * horários é limitada aos primeiros `MAX_PROFESSIONALS_FOR_ANY`
 * profissionais compatíveis (ordenados por nome) para manter o cálculo
 * limitado — nunca "todos os profissionais para sempre".
 */
final class PublicAvailabilityFinder
{
    private const MAX_PROFESSIONALS_FOR_ANY = 15;

    public function __construct(
        private readonly ProfessionalAvailabilityResolver $availabilityResolver,
        private readonly ProfessionalAvailabilityCalendarResolver $calendarResolver,
        private readonly BookedRangeResolver $bookedRangeResolver,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function eligibleUnits(Organization $organization): Collection
    {
        return $organization->units()
            ->where('status', RecordStatus::Active)
            ->whereHas('professionalLinks', fn ($query) => $query->where('status', RecordStatus::Active))
            ->with('address')
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit) => $this->mapUnit($unit))
            ->values();
    }

    /** @return array<string, mixed> */
    private function mapUnit(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'neighborhood' => $unit->address?->neighborhood,
            'city' => $unit->address?->city,
            'state' => $unit->address?->state,
        ];
    }

    /** @return Collection<int, array{id: string, name: string}> */
    public function eligibleSpecialties(Organization $organization, string $unitId): Collection
    {
        return $organization->specialties()
            ->where('status', RecordStatus::Active)
            ->whereHas('professionalLinks', function ($query) use ($unitId) {
                /** @var Builder<ProfessionalSpecialty> $query */
                $query->where('status', RecordStatus::Active)
                    ->whereHas('professional', function ($professionalQuery) use ($unitId) {
                        /** @var Builder<Professional> $professionalQuery */
                        $professionalQuery->where('status', RecordStatus::Active)
                            ->whereHas('unitLinks', fn ($unitLinkQuery) => $unitLinkQuery->where('unit_id', $unitId)->where('status', RecordStatus::Active));
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Specialty $specialty) => ['id' => $specialty->id, 'name' => $specialty->name])
            ->values();
    }

    /**
     * `$includeNonPublic` existe só para o portal do paciente (ver
     * App\Http\Controllers\PatientPortal\PatientAvailabilityController):
     * um paciente autenticado pode agendar qualquer serviço ativo da
     * própria organização, não só os marcados para vitrine pública do
     * site. A busca pública (padrão, `false`) nunca deve ver isso.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function eligibleServices(Organization $organization, string $unitId, ?string $specialtyId, bool $includeNonPublic = false): Collection
    {
        $services = $organization->services()
            ->where('status', RecordStatus::Active)
            ->when(! $includeNonPublic, fn (Builder $query) => $query->where('is_public', true))
            ->with(['unitLinks', 'specialtyLinks'])
            ->orderBy('name')
            ->get();

        return $services
            ->filter(fn (Service $service) => $service->availableUnitIds()->contains($unitId))
            ->filter(fn (Service $service) => $specialtyId === null || $service->specialtyLinks->pluck('specialty_id')->contains($specialtyId))
            ->map(fn (Service $service) => $this->mapService($service))
            ->values();
    }

    /** @return array<string, mixed> */
    private function mapService(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'default_duration_minutes' => $service->default_duration_minutes,
        ];
    }

    /** @return Collection<int, array{id: string, name: string, photo_url: null}> */
    public function eligibleProfessionals(Organization $organization, string $unitId, string $serviceId, ?string $specialtyId): Collection
    {
        return $this->compatibleProfessionalServices($organization, $unitId, $serviceId, $specialtyId)
            ->map(fn (ProfessionalService $link) => [
                'id' => $link->professional->id,
                'name' => $link->professional->display_name,
                'photo_url' => null,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{date: string, is_available: bool}>
     */
    public function availableDates(Organization $organization, string $unitId, string $serviceId, ?string $professionalId, ?string $specialtyId, CarbonInterface $month): Collection
    {
        $unit = $organization->units()->findOrFail($unitId);
        $from = $month->copy()->startOfMonth();
        $until = $month->copy()->endOfMonth();

        $candidates = $this->resolveCandidateProfessionals($organization, $unitId, $serviceId, $professionalId, $specialtyId);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $availableDates = collect();

        foreach ($candidates as $professional) {
            $window = $this->calendarResolver->resolveWindow($professional, $unit, $from, $until);

            foreach ($window as $day) {
                if ($day['is_operational']) {
                    $availableDates->push($day['date']);
                }
            }
        }

        $availableDates = $availableDates->unique();

        $cursor = $from->copy();
        $result = collect();

        while ($cursor->lte($until)) {
            $result->push(['date' => $cursor->toDateString(), 'is_available' => $availableDates->contains($cursor->toDateString())]);
            $cursor = $cursor->addDay();
        }

        return $result;
    }

    /**
     * @return Collection<int, array{time: string, professional_id: string, professional_name: string, unit_name: string, service_name: string, duration_minutes: int}>
     */
    public function availableTimes(Organization $organization, string $unitId, string $serviceId, ?string $professionalId, ?string $specialtyId, CarbonInterface $date): Collection
    {
        $unit = $organization->units()->findOrFail($unitId);
        $service = $organization->services()->findOrFail($serviceId);

        $links = $this->compatibleProfessionalServices($organization, $unitId, $serviceId, $specialtyId)
            ->when($professionalId !== null, fn (Collection $collection) => $collection->where('professional_id', $professionalId));

        $slots = collect();

        foreach ($links as $link) {
            $duration = $link->effectiveDurationMinutes();
            $bufferBefore = $link->effectiveBufferBeforeMinutes();
            $bufferAfter = $link->effectiveBufferAfterMinutes();
            $totalSpan = $bufferBefore + $duration + $bufferAfter;

            $daily = $this->availabilityResolver->resolve($link->professional, $unit, $date);
            // Nunca sugere (nem deixa o paciente concluir) um horário que
            // já tem um agendamento real ou um pré-agendamento pendente de
            // OUTRO paciente para este profissional — achado em uso real:
            // este buscador nunca subtraía reservas porque não existiam
            // quando foi escrito (ver App\Services\Availability\StaffAppointmentSlotFinder,
            // que já fazia isso do lado autenticado).
            $bookedRanges = $this->bookedRangeResolver->forProfessionalOnDate($link->professional, $unit, $date);

            foreach ($daily->effectiveIntervals as $interval) {
                $cursor = $interval->startsAt;

                while ($this->addMinutes($cursor, $totalSpan) <= $interval->endsAt) {
                    $slotStart = $this->addMinutes($cursor, $bufferBefore);
                    $slotEnd = $this->addMinutes($slotStart, $duration);

                    if (! BookedRangeResolver::overlapsAny($slotStart, $slotEnd, $bookedRanges)) {
                        $slots->push([
                            'time' => $slotStart,
                            'professional_id' => $link->professional->id,
                            'professional_name' => $link->professional->display_name,
                            'unit_name' => $unit->name,
                            'service_name' => $service->name,
                            'duration_minutes' => $duration,
                        ]);
                    }

                    $cursor = $this->addMinutes($cursor, $duration);
                }
            }
        }

        return $slots->sortBy('time')->values();
    }

    /**
     * @return Collection<int, ProfessionalService>
     */
    private function compatibleProfessionalServices(Organization $organization, string $unitId, string $serviceId, ?string $specialtyId): Collection
    {
        $links = ProfessionalService::query()
            ->where('organization_id', $organization->id)
            ->where('service_id', $serviceId)
            ->where('status', RecordStatus::Active)
            ->where('unit_scope', '!=', ProfessionalServiceUnitScope::None->value)
            ->with(['professional', 'unitLinks'])
            ->get()
            // `professional` vem null quando o vínculo aponta para um
            // profissional excluído logicamente (a global scope de
            // SoftDeletes já filtra o eager load) — DeleteProfessionalAction
            // nunca desativa retroativamente os vínculos de serviço, então
            // isso é esperado, não um erro de dados.
            ->filter(fn (ProfessionalService $link) => $link->professional !== null
                && $link->professional->status === RecordStatus::Active
                && $link->compatibleUnitIds()->contains($unitId));

        if ($specialtyId !== null) {
            $links = $links->filter(fn (ProfessionalService $link) => $link->professional->specialtyLinks()
                ->where('specialty_id', $specialtyId)
                ->where('status', RecordStatus::Active)
                ->exists());
        }

        return $links->sortBy(fn (ProfessionalService $link) => $link->professional->display_name)->values();
    }

    /** @return Collection<int, Professional> */
    private function resolveCandidateProfessionals(Organization $organization, string $unitId, string $serviceId, ?string $professionalId, ?string $specialtyId): Collection
    {
        $links = $this->compatibleProfessionalServices($organization, $unitId, $serviceId, $specialtyId);

        if ($professionalId !== null) {
            $links = $links->where('professional_id', $professionalId);
        }

        return $links
            ->take(self::MAX_PROFESSIONALS_FOR_ANY)
            ->map(fn (ProfessionalService $link) => $link->professional)
            ->values();
    }

    private function addMinutes(string $time, int $minutes): string
    {
        return Carbon::createFromFormat('H:i', $time === '24:00' ? '23:59' : $time)
            ->addMinutes($minutes)
            ->format('H:i');
    }
}
