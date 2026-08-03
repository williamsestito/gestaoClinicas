<?php

declare(strict_types=1);

use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\ProfessionalTimeBlockType;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Unit;
use App\Services\Availability\ProfessionalAvailabilityCalendarResolver;
use Illuminate\Support\Carbon;

/** @return array{0: Organization, 1: Unit, 2: Professional, 3: ProfessionalUnit} */
function calendarSetup(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create([
        'legal_entity_id' => $legalEntity->id,
        'status' => RecordStatus::Active,
        'timezone' => 'America/Sao_Paulo',
    ]);

    foreach ([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday, Weekday::Thursday, Weekday::Friday] as $weekday) {
        $unit->openingHours()->create([
            'organization_id' => $organization->id,
            'day_of_week' => $weekday->value,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'sort_order' => 0,
        ]);
    }

    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'status' => RecordStatus::Active,
    ]);

    return [$organization, $unit, $professional, $link];
}

it('resolves a window of dates marking each as operational or not', function () {
    [$organization, $unit, $professional, $link] = calendarSetup();

    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday->value,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);

    $resolver = app(ProfessionalAvailabilityCalendarResolver::class);
    $window = $resolver->resolveWindow($professional, $unit, Carbon::parse('2026-08-03'), Carbon::parse('2026-08-04'));

    expect($window->firstWhere('date', '2026-08-03')['is_operational'])->toBeTrue()
        ->and($window->firstWhere('date', '2026-08-04')['is_operational'])->toBeFalse();
});

it('rejects a window larger than the maximum allowed', function () {
    [, $unit, $professional] = calendarSetup();

    $resolver = app(ProfessionalAvailabilityCalendarResolver::class);

    expect(fn () => $resolver->resolveWindow($professional, $unit, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')))
        ->toThrow(InvalidArgumentException::class);
});

it('summarizes a vigency counting total, selected-weekday, available and blocked days', function () {
    [$organization, $unit, $professional, $link] = calendarSetup();

    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday->value,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);

    // 2026-08-10 é uma segunda-feira dentro do período — bloqueada.
    ProfessionalTimeBlock::factory()->for($organization)->for($professional)->create([
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::DayOff,
        'is_all_day' => true,
        'starts_at' => Carbon::parse('2026-08-10 00:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-11 00:00', 'America/Sao_Paulo')->utc(),
        'status' => RecordStatus::Active,
    ]);

    $resolver = app(ProfessionalAvailabilityCalendarResolver::class);
    $summary = $resolver->summarizeVigency(
        $professional,
        $unit,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-30'),
        [Weekday::Monday->value],
    );

    // Agosto/2026 tem 5 segundas-feiras (03, 10, 17, 24, 31 está fora do período de 01 a 30 — só até 24).
    expect($summary['total_days'])->toBe(30)
        ->and($summary['selected_weekday_days'])->toBe(4)
        ->and($summary['available_days'])->toBe(3)
        ->and($summary['blocked_days'])->toBe(1);
});
