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
use App\Services\Availability\ProfessionalAvailabilityResolver;
use Illuminate\Support\Carbon;

function availabilitySetup(array $unitOverrides = []): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(array_merge([
        'legal_entity_id' => $legalEntity->id,
        'status' => RecordStatus::Active,
        'timezone' => 'America/Sao_Paulo',
    ], $unitOverrides));
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'sort_order' => 0,
    ]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'status' => RecordStatus::Active,
    ]);

    return [$organization, $unit, $professional, $link];
}

// 2026-08-03 é uma segunda-feira.
function nextMonday(): Carbon
{
    return Carbon::parse('2026-08-03');
}

it('returns the regular interval unchanged when there is no time block', function () {
    [, $unit, $professional, $link] = availabilitySetup();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $professional->organization_id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->isOperational)->toBeTrue()
        ->and($result->regularIntervals)->toHaveCount(1)
        ->and($result->effectiveIntervals)->toHaveCount(1)
        ->and($result->effectiveIntervals[0]->startsAt)->toBe('08:00')
        ->and($result->effectiveIntervals[0]->endsAt)->toBe('12:00');
});

it('removes the whole interval when a time block fully covers it', function () {
    [$organization, $unit, $professional, $link] = availabilitySetup();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => Carbon::parse('2026-08-03 08:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-03 12:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => false,
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->effectiveIntervals)->toBe([])
        ->and($result->isOperational)->toBeFalse()
        ->and($result->applicableTimeBlocks)->toHaveCount(1);
});

it('splits the interval in two when a time block partially covers it', function () {
    [$organization, $unit, $professional, $link] = availabilitySetup();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => Carbon::parse('2026-08-03 09:30', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-03 10:30', 'America/Sao_Paulo')->utc(),
        'is_all_day' => false,
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->effectiveIntervals)->toHaveCount(2)
        ->and($result->effectiveIntervals[0]->toArray())->toBe(['starts_at' => '08:00', 'ends_at' => '09:30'])
        ->and($result->effectiveIntervals[1]->toArray())->toBe(['starts_at' => '10:30', 'ends_at' => '12:00']);
});

it('subtracts multiple time blocks from the same interval', function () {
    [$organization, $unit, $professional, $link] = availabilitySetup();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '18:00',
    ]);
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-03 10:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => false,
    ]);
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => Carbon::parse('2026-08-03 14:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-03 15:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => false,
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->effectiveIntervals)->toHaveCount(3)
        ->and(array_map(fn ($i) => $i->toArray(), $result->effectiveIntervals))->toBe([
            ['starts_at' => '08:00', 'ends_at' => '09:00'],
            ['starts_at' => '10:00', 'ends_at' => '14:00'],
            ['starts_at' => '15:00', 'ends_at' => '18:00'],
        ]);
});

it('reports a time block even when there is no working hour configured for that day', function () {
    [$organization, $unit, $professional] = availabilitySetup();
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Vacation,
        'starts_at' => Carbon::parse('2026-08-03 00:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-04 00:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => true,
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->regularIntervals)->toBe([])
        ->and($result->applicableTimeBlocks)->toHaveCount(1)
        ->and($result->reasons)->toContain('Sem jornada configurada para este dia.')
        ->and($result->isOperational)->toBeFalse();
});

it('does not apply a specific-unit time block from a different unit', function () {
    [$organization, $unit, $professional, $link] = availabilitySetup();
    $otherLegalEntity = LegalEntity::factory()->for($organization)->create();
    $otherUnit = Unit::factory()->for($organization)->create(['legal_entity_id' => $otherLegalEntity->id, 'status' => RecordStatus::Active]);
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $otherUnit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => Carbon::parse('2026-08-03 08:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-03 12:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => false,
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->applicableTimeBlocks)->toBe([])
        ->and($result->effectiveIntervals)->toHaveCount(1);
});

it('applies an all-units time block to every unit', function () {
    [$organization, $unit, $professional, $link] = availabilitySetup();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => null,
        'scope' => ProfessionalTimeBlockScope::AllUnits,
        'type' => ProfessionalTimeBlockType::Vacation,
        'starts_at' => Carbon::parse('2026-08-03 00:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-04 00:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => true,
    ]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->effectiveIntervals)->toBe([])
        ->and($result->applicableTimeBlocks)->toHaveCount(1);
});

it('reports the professional link as invalid when there is none for the unit', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->isOperational)->toBeFalse()
        ->and($result->reasons)->toContain('Sem vínculo ativo com a unidade.');
});

it('reports an inactive unit', function () {
    [, $unit, $professional] = availabilitySetup(['status' => RecordStatus::Inactive]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->isOperational)->toBeFalse()
        ->and($result->reasons)->toContain('Unidade inativa.');
});

it('reports an inactive professional', function () {
    [, $unit, $professional] = availabilitySetup();
    $professional->update(['status' => RecordStatus::Inactive]);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->isOperational)->toBeFalse()
        ->and($result->reasons)->toContain('Profissional inativo.');
});

it('warns when a working hour no longer fits the unit opening hours, without discarding it silently', function () {
    [$organization, $unit, $professional, $link] = availabilitySetup();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    $unit->openingHours()->update(['opens_at' => '10:00']);

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->regularIntervals)->toBe([])
        ->and($result->warnings)->toHaveCount(1)
        ->and(ProfessionalWorkingHour::query()->count())->toBe(1);
});

it('never returns a persisted slot representation — only computed intervals', function () {
    [, $unit, $professional] = availabilitySetup();

    $result = (new ProfessionalAvailabilityResolver)->resolve($professional, $unit, nextMonday());

    expect($result->toArray())->toHaveKeys(['is_operational', 'timezone', 'weekday', 'regular_intervals', 'effective_intervals', 'applicable_time_blocks', 'reasons', 'warnings']);
});
